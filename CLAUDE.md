Positioning and voice: read `docs/positioning.md` before writing any user-facing copy, or invoke the `revisemy-voice` skill.

ReviseMy is Laravel 13, Livewire 4 and Flux, with Tailwind 4 configured in `resources/css/app.css`. Its look is a close cousin of Koati's (same studio): tokens live in `resources/css/tokens.css`. Style with the tokens and the theme-aware palette in `app.css`, never raw hex, so light and dark both hold.

- Tests: `php artisan test`. Lint: `vendor/bin/pint --test`. Assets: `npm run build`. Browser tests: `npm run test:e2e`.
- Mark, status and severity colours are decided in PHP (`App\Models\Annotation`), not in views. Change them there.
- The MCP Apps inline review (`resources/views/mcp/review-app.blade.php`) must stay in step with the web review and board. See `docs/CONNECTORS.md` § MCP ↔ web parity checklist, and ship both in the same change.
- Commit subjects are imperative sentence case and read as plain sentences.
