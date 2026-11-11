import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { appEnv, path, ROOT } from './support/app';

test('page preview keeps its mode and browser dimensions when switching from desktop to tablet and phone', async ({ page }) => {
    const url = execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['revisemy.capture.driver' => 'hosted', 'revisemy.capture.endpoint' => 'https://capture.test/screenshot']);
        Illuminate\Support\Facades\Http::fake(function ($request) {
            $viewport = $request->data()['context']['viewport'];
            ['width' => $width, 'height' => $height] = $viewport;
            $image = imagecreatetruecolor($width, 1800);
            ob_start();
            imagepng($image);
            return Illuminate\Support\Facades\Http::response([
                'image' => base64_encode(ob_get_clean()),
                'html' => "<!doctype html><style>body{margin:0} #hero{height:100vh} footer{height:1000px}</style><section id=hero>Captured width {$width}</section><footer>End of page</footer>",
                'css' => ['width' => $width, 'height' => 1800],
                'stable' => true,
                'loaded' => true,
            ]);
        });
        $user = app(App\Services\TryTokenService::class)->create()['user'];
        $review = app(App\Services\ReviewService::class)->createFromRequest($user->workspace, [
            'title' => 'Device preview',
            'page_url' => 'https://example.com/',
            'capture_url' => true,
        ]);
        echo $review->reviewUrl();
    `], { cwd: ROOT, env: { ...process.env, ...appEnv() }, encoding: 'utf8' });

    await page.goto(path(url));
    await page.getByRole('tab', { name: 'Page preview' }).click();
    const frame = page.frameLocator('iframe[title="Saved page preview"]');
    const checkSize = async (width: number, height: number) => {
        const hero = frame.getByText('Captured width ' + width, { exact: true });
        await expect(hero).toBeVisible();
        expect(await hero.evaluate((element) => [innerWidth, innerHeight, element.clientHeight])).toEqual([width, height, height]);
    };
    await checkSize(1280, 800);

    await page.getByRole('button', { name: 'Viewport: Desktop' }).click();
    await page.getByRole('menuitem', { name: /^Tablet/ }).click();
    await checkSize(768, 1024);

    await page.getByRole('button', { name: 'Viewport: Tablet' }).click();
    await page.getByRole('menuitem', { name: /^Mobile/ }).click();
    await checkSize(375, 812);
    await expect(page.getByRole('link', { name: 'Open live site' })).toHaveAttribute('href', 'https://example.com/');
});
