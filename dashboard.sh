#!/bin/bash
# dashboard.sh — Terminal client for analytics

API_URL="https://go.tyclifford.com/api.php"
API_KEY=""

while true; do
    clear
    echo "==== Shortener Analytics Dashboard ===="
    echo "Updated: $(date)"
    echo

    # Fetch JSON from API
    RESPONSE=$(curl -s "${API_URL}?key=${API_KEY}")

    # Show total clicks (default 0 if null/missing)
    TOTAL=$(echo "$RESPONSE" | jq -r '.total // 0')
    echo "Total Clicks: $TOTAL"
    echo

    # Top Shorts
    echo "Top Short Links:"
    echo "$RESPONSE" | jq -r '.topShorts // [] | .[] | "  \(.short): \(.c)"'
    echo

    # Top Countries
    echo "Top Countries:"
    echo "$RESPONSE" | jq -r '.topCountries // [] | .[] | "  \(.country): \(.c)"'
    echo

    # Recent Visits (with City, fallback if missing)
    echo "Recent Visits:"
    echo "$RESPONSE" | jq -r '.recent // [] | .[] | "  [\(.ts // "-")] \(.short // "-") from \(.city // "Unknown"), \(.country // "??") (\(.ip // "N/A"))"'
    echo

    # Refresh every 10s
    sleep 10
done
