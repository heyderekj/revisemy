User-agent: *
Allow: /
Disallow: /r/

# Link previews: these fetch a review link only when a person pastes it,
# and never index it (review pages also send noindex).
User-agent: Slackbot-LinkExpanding
User-agent: Twitterbot
User-agent: facebookexternalhit
User-agent: LinkedInBot
User-agent: Discordbot
Allow: /

Sitemap: {{ rtrim(config('app.url'), '/') }}/sitemap.xml

# For language models and agents:
# {{ rtrim(config('app.url'), '/') }}/llms.txt
# {{ rtrim(config('app.url'), '/') }}/llms-full.txt
# MCP server card: {{ rtrim(config('app.url'), '/') }}/.well-known/mcp/server-card.json
