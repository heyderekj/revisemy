<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmitReviewTest extends TestCase
{
    use RefreshDatabase;

    /** A public address as an IP, so the SSRF guard needs no DNS. */
    protected const PAGE = 'https://93.184.215.14/pricing';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.capture.driver' => 'hosted',
            'revisemy.capture.endpoint' => 'https://capture.test/screenshot',
            'revisemy.capture.api_key' => 'cap-key',
        ]);
    }

    protected function tinyPngBinary(): string
    {
        return hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );
    }

    protected function fakeCapture(): void
    {
        Http::fake([
            'capture.test/function*' => Http::response('Not Found', 404),
            'capture.test/*' => Http::response($this->tinyPngBinary()),
        ]);
    }

    public function test_the_page_opens_on_the_tab_asked_for(): void
    {
        $this->get('/new')->assertOk()->assertSee('Start a review')->assertSee('Your first review makes a try workspace');
        $this->get('/new?kind=pdf')->assertOk()->assertSee("reviewSubmit('pdf')", false);
        $this->get('/new.md')->assertOk()->assertSee('# Start a review');
    }

    public function test_a_link_makes_a_web_review_and_signs_the_browser_in(): void
    {
        $this->fakeCapture();

        $response = $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE, 'context' => 'The pricing table']);

        $review = Review::query()->sole();
        $response->assertRedirect(route('reviews.show', $review->token));

        $this->assertSame(Review::ORIGIN_WEB, $review->origin);
        $this->assertNull($review->handed_off_at);
        $this->assertSame(Review::TYPE_WEBSITE, $review->type);
        $this->assertSame(self::PAGE, $review->page_url);
        $this->assertSame('93.184.215.14', $review->title);
        $this->assertSame('The pricing table', $review->context);
        $this->assertAuthenticatedAs(User::query()->sole(), 'web');
        $this->assertSame(40 - 5, $review->workspace->fresh()->credits_balance);
    }

    public function test_a_link_without_a_scheme_is_read_as_https(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => '93.184.215.14/pricing']);

        $this->assertSame(self::PAGE, Review::query()->sole()->page_url);
    }

    public function test_screenshots_make_a_review_titled_after_the_first_file(): void
    {
        $this->post('/new', [
            'kind' => 'images',
            'images' => [
                UploadedFile::fake()->createWithContent('checkout-mobile.png', $this->tinyPngBinary()),
                UploadedFile::fake()->createWithContent('checkout-desktop.png', $this->tinyPngBinary()),
            ],
        ])->assertRedirect();

        $review = Review::query()->sole();
        $this->assertSame('checkout-mobile', $review->title);
        $this->assertSame(Review::ORIGIN_WEB, $review->origin);
        $this->assertCount(2, $review->screenshots);
    }

    public function test_an_email_file_renders_as_an_email_review(): void
    {
        $this->fakeCapture();

        $this->post('/new', [
            'kind' => 'html',
            'html' => UploadedFile::fake()->createWithContent('welcome.html', '<html><body><h1>Welcome</h1></body></html>'),
        ])->assertRedirect();

        $review = Review::query()->sole();
        $this->assertSame(Review::TYPE_EMAIL, $review->type);
        $this->assertSame('welcome', $review->title);
    }

    public function test_a_pdf_becomes_slides_where_this_host_can_render_one(): void
    {
        $pdf = base64_decode('JVBERi0xLjQKMSAwIG9iago8PCAvVHlwZSAvQ2F0YWxvZyAvUGFnZXMgMiAwIFIgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL1R5cGUgL1BhZ2VzIC9LaWRzIFszIDAgUl0gL0NvdW50IDEgPj4KZW5kb2JqCjMgMCBvYmoKPDwgL1R5cGUgL1BhZ2UgL1BhcmVudCAyIDAgUiAvTWVkaWFCb3ggWzAgMCAyMDAgMjAwXSA+PgplbmRvYmoKeHJlZgowIDQKMDAwMDAwMDAwMCA2NTUzNSBmIAowMDAwMDAwMDA5IDAwMDAwIG4gCjAwMDAwMDAwNTggMDAwMDAgbiAKMDAwMDAwMDExNSAwMDAwMCBuIAp0cmFpbGVyCjw8IC9TaXplIDQgL1Jvb3QgMSAwIFIgPj4Kc3RhcnR4cmVmCjE4NgolJUVPRgo=');

        $response = $this->post('/new', [
            'kind' => 'pdf',
            'pdf' => UploadedFile::fake()->createWithContent('launch-deck.pdf', $pdf),
        ]);

        $review = Review::query()->first();

        if (! $review) {
            // No Imagick PDF coder here: the reason comes back on the form.
            $response->assertRedirect(route('reviews.new', ['kind' => 'pdf']))->assertSessionHasErrors('pdf');
            $this->markTestSkipped('This host cannot render PDFs.');
        }

        $this->assertSame(Review::TYPE_PRESENTATION, $review->type);
        $this->assertSame('launch-deck', $review->title);
    }

    public function test_a_second_review_from_the_same_browser_uses_the_same_workspace(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);

        $this->assertSame(1, Workspace::query()->count());
        $this->assertSame(2, Review::query()->count());
        $this->assertSame(40 - 10, Workspace::query()->sole()->credits_balance);
    }

    public function test_private_and_loopback_pages_are_refused(): void
    {
        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/'] as $url) {
            $this->post('/new', ['kind' => 'link', 'page_url' => $url])
                ->assertRedirect(route('reviews.new', ['kind' => 'link']))
                ->assertSessionHasErrors('page_url');
        }

        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, Workspace::query()->count());
    }

    public function test_the_wrong_kind_of_file_is_refused(): void
    {
        $this->post('/new', [
            'kind' => 'images',
            'images' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
        ])->assertSessionHasErrors('images.0');

        $this->assertSame(0, Review::query()->count());
    }

    public function test_too_many_reviews_from_one_network_are_refused(): void
    {
        $this->fakeCapture();
        config(['billing.try_token.submit_per_hour' => 2]);

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE])
            ->assertSessionHasErrors(['page_url' => 'Too many reviews started from this network in the last hour. Try again in a little while.']);

        $this->assertSame(2, Review::query()->count());
    }

    /**
     * The progress dialog's request: each step as it happens, then the review.
     *
     * @return list<array<string, mixed>>
     */
    protected function streamed(array $form): array
    {
        $response = $this->post('/new', $form, ['Accept' => 'text/event-stream'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        return collect(explode("\n\n", trim($response->streamedContent())))
            ->map(fn (string $event) => json_decode(substr($event, strlen('data: ')), true))
            ->all();
    }

    public function test_the_dialog_hears_each_viewport_land_then_where_the_review_is(): void
    {
        $this->fakeCapture();

        $events = collect($this->streamed(['kind' => 'link', 'page_url' => self::PAGE]));
        $steps = $events->pluck('step')->filter()->values()->all();
        $review = Review::query()->sole();

        $this->assertSame('open', $steps[0]);
        $this->assertContains('shoot', $steps);
        $this->assertContains('shot:desktop-1280', $steps);
        $this->assertContains('shot:mobile-375', $steps);
        $this->assertContains('shot:tablet-768', $steps);
        $this->assertSame('save', end($steps));
        $this->assertSame(['done' => route('reviews.show', $review->token)], $events->last());
        $this->assertSame(Review::ORIGIN_WEB, $review->origin);
        $this->assertAuthenticatedAs(User::query()->sole(), 'web');
    }

    public function test_the_dialog_gets_a_bad_address_back_as_json(): void
    {
        $this->post('/new', ['kind' => 'link', 'page_url' => 'http://127.0.0.1/admin'], ['Accept' => 'text/event-stream'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['page_url']]);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_the_dialog_hears_when_credits_run_out(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        Workspace::query()->sole()->update(['credits_balance' => 2, 'purchased_credits' => 0]);

        $events = $this->streamed(['kind' => 'link', 'page_url' => self::PAGE]);

        $this->assertSame(['errors' => ['page_url' => 'That needs 5 credits and this browser’s try workspace has 2 left. Credits renew monthly.']], end($events));
        $this->assertSame(1, Review::query()->count());
        $this->assertSame(2, Workspace::query()->sole()->credits_balance);
    }

    public function test_the_dialog_streams_a_pdf_too(): void
    {
        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);

        $events = collect($this->streamed([
            'kind' => 'images',
            'images' => [UploadedFile::fake()->createWithContent('home.png', $this->tinyPngBinary())],
        ]));

        $this->assertSame('save', $events->pluck('step')->filter()->last());
        $this->assertStringContainsString('/r/', $events->last()['done']);
    }

    public function test_running_out_of_credits_says_so_plainly(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        Workspace::query()->sole()->update(['credits_balance' => 2, 'purchased_credits' => 0]);

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE])
            ->assertSessionHasErrors(['page_url' => 'That needs 5 credits and this browser’s try workspace has 2 left. Credits renew monthly.']);

        $this->assertSame(1, Review::query()->count());
    }

    public function test_the_review_shows_what_to_paste_to_an_agent(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);
        $review = Review::query()->sole();

        $prompt = 'Open my ReviseMy review '.$review->reviewUrl().' and work through my marks.';

        $this->get(route('reviews.show', $review->token))
            ->assertOk()
            ->assertSee('Hand this to your agent')
            ->assertSeeInOrder(['Mark what matters', 'Drag on the page to mark a spot', 'Ask for changes', 'Send it to your agent'])
            ->assertSee($prompt)
            ->assertSee(e('https://claude.ai/new?q='.rawurlencode($prompt)), false)
            ->assertSee(e('https://chatgpt.com/?q='.rawurlencode($prompt)), false)
            ->assertSee(e('https://cursor.com/link/prompt?text='.rawurlencode($prompt)), false)
            ->assertSee('Your agent needs ReviseMy connected first.');

        // A guest never sees it.
        $this->get('/r/'.$review->share_token)->assertOk()->assertDontSee('Hand this to your agent');

        $review->update(['handed_off_at' => now()]);

        $this->get(route('reviews.show', $review->token))
            ->assertDontSee('Hand this to your agent')
            ->assertSee('Your agent has this review');

        $review->update(['handed_off_to' => 'Claude']);

        $this->get(route('reviews.show', $review->token))->assertSee('Claude has this review');
    }

    public function test_your_reviews_lists_what_this_browser_started(): void
    {
        $this->fakeCapture();

        $this->post('/new', ['kind' => 'link', 'page_url' => self::PAGE]);

        $this->get('/reviews')->assertOk()->assertSee('93.184.215.14');
    }

    public function test_the_home_hero_starts_a_review_here(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('reviews.new').'"', false)
            ->assertDontSee('Paste a page to review');
    }
}
