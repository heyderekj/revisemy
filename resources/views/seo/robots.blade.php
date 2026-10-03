User-agent: *
Allow: /
Disallow: /r/

Sitemap: {{ rtrim(config('app.url'), '/') }}/sitemap.xml

# For language models and agents:
# {{ rtrim(config('app.url'), '/') }}/llms.txt
# {{ rtrim(config('app.url'), '/') }}/llms-full.txt
# MCP server card: {{ rtrim(config('app.url'), '/') }}/.well-known/mcp/server-card.json
