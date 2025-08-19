#!/bin/bash
# urlgen.sh - Add short URL entry via PHP API and copy to clipboard

# ==== CONFIGURATION ==== #
API_KEY=""
API_ENDPOINT="https://go.tyclifford.com/url.php"
# ======================= #

if [ $# -lt 1 ] || [ $# -gt 2 ]; then
    echo "Usage: $0 <URL> [customName]"
    exit 1
fi

URL="$1"
SHORT="$2"

# Build curl command dynamically
if [ -n "$SHORT" ]; then
    response=$(curl -sG \
        --data-urlencode "key=$API_KEY" \
        --data-urlencode "url=$URL" \
        --data-urlencode "short=$SHORT" \
        "$API_ENDPOINT")
else
    response=$(curl -sG \
        --data-urlencode "key=$API_KEY" \
        --data-urlencode "url=$URL" \
        "$API_ENDPOINT")
fi

# Output the API's raw JSON
echo "$response"

# Extract shortLink from JSON using jq
shortLink=$(echo "$response" | jq -r '.shortLink // empty')

# If found, copy to clipboard
if [ -n "$shortLink" ]; then
    echo -n "$shortLink" | xclip -selection clipboard
    echo "✅ Short link copied to clipboard: $shortLink"
else
    echo "❌ No short link found in response."
fi
