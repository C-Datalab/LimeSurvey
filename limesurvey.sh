#!/bin/bash
set -e
cd "$(dirname "$0")"

if [ ! -f .env ]; then
    echo ""
    echo "  ERROR: .env file not found."
    echo ""
    echo "  Please create one before starting:"
    echo "    cp .env.example .env"
    echo "    # then edit .env and set your passwords and BASE_URL"
    echo ""
    exit 1
fi

docker compose down
docker compose up -d
docker compose logs -f
