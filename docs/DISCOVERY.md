# Discovery

How people and agents find ReviseMy, and what's left to do by hand. The site handles its part on its own; the list at the end needs Derek's accounts, so nothing there happens automatically.

Last updated: 2026-10-03

## What the site already serves

| For | Where | Built from |
|---|---|---|
| Search engines | `/sitemap.xml` (with `lastmod`), `/robots.txt`, canonical links on the main host, JSON-LD (SoftwareApplication, WebAPI, FAQPage, BreadcrumbList) | `App\Support\MarketingPages`, `App\Support\PageSchema`, `components/seo-head.blade.php` |
| Answer engines and agents | `/llms.txt`, `/llms-full.txt`, and every public page as markdown (`/board.md`, `/for/websites.md`, `/index.md`) | `App\Support\PageMarkdown`, the page configs |
| MCP clients | `/.well-known/mcp/server-card.json` (alias `/.well-known/mcp.json`), plus the OAuth discovery documents | `App\Support\McpCatalog`, which reads the registered tools and prompts |
| The MCP registry | `server.json` at the repo root | Hand-written; `revisemy:bump` keeps its version in step |
| Claude Code | The plugin in `plugin/`, listed by `.claude-plugin/marketplace.json` | Hand-written skill in `plugin/skills/design-checkup/SKILL.md` |

The tool list in llms.txt, the server card and the structured data all come from `McpCatalog`. When you add or rename a tool, there's nothing else to update. `tests/Feature/DiscoveryTest.php` fails if the plugin's skill names a tool the server doesn't have.

## To do (needs Derek's accounts)

Do these once PR #10, #11 and the discovery PR have merged and deployed, so the listings point at live pages.

### MCP registry (the source many directories copy from)

```bash
brew install mcp-publisher
```

```bash
mcp-publisher login github
```

```bash
mcp-publisher publish
```

Run them from the repo root. The `io.github.heyderekj/` namespace is proven by the GitHub login. Publish again after each release; `revisemy:bump` already updated the version in `server.json`.

### Directories

- **Anthropic's plugin directory**: submit the plugin in `plugin/` (see the Claude Code docs, "Submit to Anthropic's directory").
- **Smithery** (smithery.ai): add a remote server with `https://revisemy.com/mcp/revisemy`.
- **Glama** (glama.ai/mcp/servers): it indexes public GitHub repos; claim the listing so the description and icon are yours.
- **PulseMCP** (pulsemcp.com) and **mcp.so**: submit the repo and the hosted URL.
- **Cursor directory** (cursor.directory): submit with the Add to Cursor link from the README.
- **awesome-mcp-servers** (github.com/punkpeye/awesome-mcp-servers): open a PR adding one line under a design or developer-tools heading.

### GitHub

Repo topics help GitHub search and the directories that read it:

```bash
gh repo edit heyderekj/revisemy --add-topic mcp,mcp-server,design-review,visual-feedback,ai-agents,laravel,human-in-the-loop
```

Cut a GitHub release for each version, so the README's release badge and the `releaseNotes` link aren't empty.

### Search

- **Google Search Console** and **Bing Webmaster Tools**: verify revisemy.com and submit `https://revisemy.com/sitemap.xml`. Bing's index also feeds several AI answer engines.
- After the first crawl, check that `/connect`, `/reviews` and `/r/…` show as excluded by noindex. That's on purpose.

### Worth a post

A short write-up gets linked and quoted more than any listing:
- Laravel News (submit a link): the Laravel MCP angle.
- Show HN: "Visual feedback for your coding agent".
- The heyderekj.com project page: link to `/connectors` and the plugin.
