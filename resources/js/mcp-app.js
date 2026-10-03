// Alpine for the inline review (resources/views/mcp/review-app.blade.php),
// bundled so the sandboxed iframe loads nothing from a CDN. Inlined as a
// module, it runs after the classic bridge script that defines reviewApp().
import Alpine from 'alpinejs';
import elementSnap from './element-snap';

window.Alpine = Alpine;
window.rmElementSnap = elementSnap;
Alpine.start();
