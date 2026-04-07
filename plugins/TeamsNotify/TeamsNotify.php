<?php
/**
 * TeamsNotify — LimeSurvey plugin
 *
 * Posts a notification to a Microsoft Teams channel whenever a survey response
 * is submitted.
 *
 * Supports three payload formats:
 *
 *   - "powerautomate"  → Clean named-field JSON.  Use this with a Power Automate
 *                        flow that has a "When a HTTP request is received" trigger.
 *                        The flow reads the fields and posts a rich card to Teams.
 *                        THIS IS THE RECOMMENDED FORMAT for most setups.
 *
 *   - "adaptivecard"   → Adaptive Card inside a `type:message` envelope.
 *                        Use this if you have the Teams Workflows webhook
 *                        ("Post to a channel when a webhook request is received").
 *
 *   - "messagecard"    → Legacy MessageCard format.
 *                        Use this with the old Office 365 Incoming Webhook connector
 *                        (still works but being phased out by Microsoft).
 *
 * Respondent contact fields are read from the response table.  Configure the
 * subquestion codes to match your survey design; the contact-info.lsq template
 * uses "name" and "email" by default.
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
            'label'   => 'Webhook URL',
            'help'    => 'Power Automate HTTP trigger URL, Teams Workflows webhook URL, or legacy Office 365 connector URL.',
            'default' => '',
        ],
        'payload_format' => [
            'type'    => 'select',
            'label'   => 'Payload format',
            'help'    => '"Power Automate" for a PA flow with HTTP trigger (recommended); "Adaptive Card" for Teams Workflows webhook; "Legacy MessageCard" for old connectors.',
            'options' => [
                'powerautomate' => 'Power Automate (HTTP trigger — recommended)',
                'adaptivecard'  => 'Adaptive Card (Teams Workflows webhook)',
                'messagecard'   => 'Legacy MessageCard (Office 365 Connector)',
            ],
            'default' => 'powerautomate',
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

        $format = $this->get('payload_format', null, null, 'powerautomate');

        switch ($format) {
            case 'messagecard':
                $payload = $this->buildMessageCard($surveyTitle, $responseId, $timestamp, $adminUrl, $respondentName, $respondentEmail);
                break;
            case 'adaptivecard':
                $payload = $this->buildAdaptiveCard($surveyTitle, $responseId, $timestamp, $adminUrl, $respondentName, $respondentEmail);
                break;
            default: // powerautomate
                $payload = $this->buildPowerAutomatePayload($surveyTitle, $surveyId, $responseId, $timestamp, $adminUrl, $respondentName, $respondentEmail);
                break;
        }

        $this->postJson($webhookUrl, $payload);
    }

    // -------------------------------------------------------------------------
    // Payload builders
    // -------------------------------------------------------------------------

    /**
     * Clean named-field JSON for a Power Automate "When a HTTP request is received" trigger.
     *
     * Power Automate parses this using the JSON schema defined on the trigger,
     * making each field available as dynamic content for use in the flow steps.
     *
     * Null fields (respondent_name, respondent_email) are omitted entirely so
     * Power Automate conditions ("is not empty") work reliably.
     */
    private function buildPowerAutomatePayload(
        string  $surveyTitle,
        int     $surveyId,
        int     $responseId,
        string  $timestamp,
        string  $adminUrl,
        ?string $respondentName,
        ?string $respondentEmail
    ): array {
        $payload = [
            'survey_title' => $surveyTitle,
            'survey_id'    => $surveyId,
            'response_id'  => $responseId,
            'timestamp'    => $timestamp,
            'admin_url'    => $adminUrl,
        ];

        if ($respondentName !== null) {
            $payload['respondent_name'] = $respondentName;
        }
        if ($respondentEmail !== null) {
            $payload['respondent_email'] = $respondentEmail;
        }

        return $payload;
    }

    /**
     * Adaptive Card payload — works with the Teams "Workflows" webhook
     * ("Post to a channel when a webhook request is received").
     */
    private function buildAdaptiveCard(
        string  $surveyTitle,
        int     $responseId,
        string  $timestamp,
        string  $adminUrl,
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
     * Microsoft is phasing these out; prefer Power Automate format for new setups.
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
