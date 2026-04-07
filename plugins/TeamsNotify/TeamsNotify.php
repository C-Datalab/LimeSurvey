<?php
/**
 * TeamsNotify — LimeSurvey plugin
 *
 * Posts a notification to a Microsoft Teams channel (via an Incoming Webhook
 * URL) whenever a survey response is submitted.
 *
 * Supports two payload formats:
 *   - "adaptivecard"  → Adaptive Card inside a `type:message` envelope.
 *                       Use this with the new Teams Workflows webhook
 *                       ("Post to a channel when a webhook request is received").
 *   - "messagecard"   → Legacy MessageCard format.
 *                       Use this with the old Office 365 Incoming Webhook connector
 *                       (still works but being phased out by Microsoft).
 *
 * Respondent contact fields are read from the response table.  Configure the
 * subquestion codes below to match your survey design; the contact-info.lsq
 * template uses "email" for the email subquestion by default.
 */
class TeamsNotify extends PluginBase
{
    protected $storage = 'DbStorage';

    static protected $name        = 'TeamsNotify';
    static protected $description = 'Send a Microsoft Teams notification when a survey response is submitted.';

    /** Plugin settings — configurable via Admin → Plugins → TeamsNotify → Settings */
    protected $settings = [
        'webhook_url' => [
            'type'    => 'string',
            'label'   => 'Teams Webhook URL',
            'help'    => 'Paste the webhook URL from your Teams channel Workflow or Incoming Webhook connector.',
            'default' => '',
        ],
        'payload_format' => [
            'type'    => 'select',
            'label'   => 'Payload format',
            'help'    => '"Adaptive Card" for new Teams Workflows; "Legacy MessageCard" for old Incoming Webhook connectors.',
            'options' => [
                'adaptivecard' => 'Adaptive Card (Teams Workflows / Power Automate)',
                'messagecard'  => 'Legacy MessageCard (Office 365 Connector)',
            ],
            'default' => 'adaptivecard',
        ],
        'name_subquestion_code' => [
            'type'    => 'string',
            'label'   => 'Name subquestion code (optional)',
            'help'    => 'Subquestion code for the respondent\'s name field, e.g. "name". Leave blank to omit.',
            'default' => 'name',
        ],
        'email_subquestion_code' => [
            'type'    => 'string',
            'label'   => 'Email subquestion code (optional)',
            'help'    => 'Subquestion code for the respondent\'s email field, e.g. "email" (matches the contact-info template). Leave blank to omit.',
            'default' => 'email',
        ],
        'enabled_surveys' => [
            'type'    => 'string',
            'label'   => 'Restrict to survey IDs (optional)',
            'help'    => 'Comma-separated list of survey IDs to notify on. Leave blank to notify for ALL surveys.',
            'default' => '',
        ],
    ];

    // -------------------------------------------------------------------------

    public function init(): void
    {
        $this->subscribe('afterSurveyComplete');
    }

    // -------------------------------------------------------------------------

    public function afterSurveyComplete(): void
    {
        $webhookUrl = trim((string) $this->get('webhook_url'));
        if ($webhookUrl === '') {
            return;
        }

        $event      = $this->getEvent();
        $surveyId   = (int) $event->get('surveyId');
        $responseId = (int) $event->get('responseId');

        // Optionally restrict to specific surveys
        $allowed = $this->get('enabled_surveys');
        if ($allowed !== '' && $allowed !== null) {
            $ids = array_filter(array_map('trim', explode(',', $allowed)));
            if (!in_array((string) $surveyId, $ids, true)) {
                return;
            }
        }

        $surveyTitle = $this->getSurveyTitle($surveyId);
        $adminUrl    = $this->buildAdminUrl($surveyId, $responseId);
        $timestamp   = date('Y-m-d H:i:s') . ' ' . date_default_timezone_get();

        [$respondentName, $respondentEmail] = $this->extractContactInfo($surveyId, $responseId);

        $format  = $this->get('payload_format', null, null, 'adaptivecard');
        $payload = ($format === 'messagecard')
            ? $this->buildMessageCard($surveyTitle, $responseId, $timestamp, $adminUrl, $respondentName, $respondentEmail)
            : $this->buildAdaptiveCard($surveyTitle, $responseId, $timestamp, $adminUrl, $respondentName, $respondentEmail);

        $this->postJson($webhookUrl, $payload);
    }

    // -------------------------------------------------------------------------
    // Payload builders
    // -------------------------------------------------------------------------

    /**
     * Adaptive Card payload — works with the Teams "Workflows" webhook.
     *
     * The Workflows webhook expects the `type:message` + `attachments` envelope
     * when the flow is configured to pass through Adaptive Cards.
     */
    private function buildAdaptiveCard(
        string $surveyTitle,
        int    $responseId,
        string $timestamp,
        string $adminUrl,
        ?string $respondentName,
        ?string $respondentEmail
    ): array {
        $facts = [
            ['title' => 'Survey',      'value' => $surveyTitle],
            ['title' => 'Response ID', 'value' => "#$responseId"],
            ['title' => 'Submitted',   'value' => $timestamp],
        ];

        if ($respondentName !== null) {
            $facts[] = ['title' => 'Name',  'value' => $respondentName];
        }
        if ($respondentEmail !== null) {
            $facts[] = ['title' => 'Email', 'value' => $respondentEmail];
        }

        return [
            'type'        => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl'  => null,
                    'content'     => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type'    => 'AdaptiveCard',
                        'version' => '1.4',
                        'body'    => [
                            [
                                'type'   => 'TextBlock',
                                'text'   => "\U0001F4CB New Survey Response",
                                'weight' => 'Bolder',
                                'size'   => 'Medium',
                                'wrap'   => true,
                            ],
                            [
                                'type'  => 'FactSet',
                                'facts' => $facts,
                            ],
                        ],
                        'actions' => [
                            [
                                'type'  => 'Action.OpenUrl',
                                'title' => 'View Response in LimeSurvey',
                                'url'   => $adminUrl,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Legacy MessageCard payload — works with old Office 365 Incoming Webhook connectors.
     * Microsoft is phasing these out; prefer Adaptive Card format for new setups.
     */
    private function buildMessageCard(
        string  $surveyTitle,
        int     $responseId,
        string  $timestamp,
        string  $adminUrl,
        ?string $respondentName,
        ?string $respondentEmail
    ): array {
        $facts = [
            ['name' => 'Survey',      'value' => $surveyTitle],
            ['name' => 'Response ID', 'value' => "#$responseId"],
            ['name' => 'Submitted',   'value' => $timestamp],
        ];

        if ($respondentName !== null) {
            $facts[] = ['name' => 'Name',  'value' => $respondentName];
        }
        if ($respondentEmail !== null) {
            $facts[] = ['name' => 'Email', 'value' => $respondentEmail];
        }

        return [
            '@type'           => 'MessageCard',
            '@context'        => 'https://schema.org/extensions',
            'summary'         => "New response: $surveyTitle",
            'themeColor'      => '0076D7',
            'title'           => 'New Survey Response',
            'sections'        => [['facts' => $facts]],
            'potentialAction' => [
                [
                    '@type'   => 'OpenUri',
                    'name'    => 'View Response',
                    'targets' => [['os' => 'default', 'uri' => $adminUrl]],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getSurveyTitle(int $surveyId): string
    {
        $survey = Survey::model()->findByPk($surveyId);
        if ($survey) {
            return $survey->getLocalizedTitle();
        }
        return "Survey #$surveyId";
    }

    private function buildAdminUrl(int $surveyId, int $responseId): string
    {
        $base = rtrim(Yii::app()->getBaseUrl(true), '/');
        return "$base/index.php/admin/responses/sa/view/surveyid/$surveyId/id/$responseId";
    }

    /**
     * Scan the response row for columns whose name ends with [<subquestionCode>].
     * Returns [name, email] — either may be null if not found or not configured.
     */
    private function extractContactInfo(int $surveyId, int $responseId): array
    {
        $nameCode  = trim((string) $this->get('name_subquestion_code'));
        $emailCode = trim((string) $this->get('email_subquestion_code'));

        if ($nameCode === '' && $emailCode === '') {
            return [null, null];
        }

        try {
            $response = Response::model($surveyId)->findByPk($responseId);
            if (!$response) {
                return [null, null];
            }

            $name  = null;
            $email = null;

            foreach ($response->attributes as $col => $value) {
                if (empty($value)) {
                    continue;
                }
                if ($nameCode !== '' && $name === null) {
                    if (preg_match('/\[' . preg_quote($nameCode, '/') . '\]$/i', $col)) {
                        $name = (string) $value;
                    }
                }
                if ($emailCode !== '' && $email === null) {
                    if (preg_match('/\[' . preg_quote($emailCode, '/') . '\]$/i', $col)) {
                        $email = (string) $value;
                    }
                }
            }

            return [$name, $email];
        } catch (Exception $e) {
            // Non-fatal — response table may not exist for very new surveys
            return [null, null];
        }
    }

    /**
     * POST JSON to the webhook URL.  Failures are silent — we don't want a
     * broken Teams integration to block survey completion for respondents.
     */
    private function postJson(string $url, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
