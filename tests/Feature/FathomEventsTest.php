<?php

namespace Tests\Feature;

use App\Services\BillingService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FathomEventsTest extends TestCase
{
    use RefreshDatabase;

    protected const CHECKOUT = '975efb1a-bee1-488c-83a5-71b73dc2cc7a';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seo.fathom_site_id' => 'TESTSITE',
            'billing.pricing_enabled' => true,
            'billing.polar.server' => 'sandbox',
            'billing.polar.access_token' => 'polar_oat_test',
            'billing.polar.webhook_secret' => 'whsec_'.base64_encode('test'),
            'billing.polar.products.plus' => 'prod_plus',
            'billing.polar.products.credits_50' => 'prod_pack',
        ]);
    }

    public function test_a_plus_purchase_reports_its_price_in_cents_once_per_tab(): void
    {
        $this->get('/billing/success?checkout_id='.self::CHECKOUT.'&product=plus')
            ->assertOk()
            ->assertSee("fathom.trackEvent('Plus purchased', { _value: 900 })", false)
            ->assertSee("'rm-fathom:".self::CHECKOUT."'", false);
    }

    public function test_a_pack_purchase_reports_the_pack_price(): void
    {
        $this->get('/billing/success?checkout_id='.self::CHECKOUT.'&product=credits_50')
            ->assertOk()
            ->assertSee("fathom.trackEvent('Credit pack purchased', { _value: 500 })", false);
    }

    public function test_the_value_comes_from_config_not_the_url(): void
    {
        $this->get('/billing/success?checkout_id='.self::CHECKOUT.'&product=plus&value=1&_value=1&amount=1')
            ->assertOk()
            ->assertSee("fathom.trackEvent('Plus purchased', { _value: 900 })", false)
            ->assertDontSee('_value: 1 ', false);
    }

    public function test_no_event_without_a_real_looking_checkout_id_or_a_known_product(): void
    {
        foreach ([
            '/billing/success',
            '/billing/success?product=plus',
            '/billing/success?checkout_id=nope&product=plus',
            '/billing/success?checkout_id='.self::CHECKOUT,
            '/billing/success?checkout_id='.self::CHECKOUT.'&product=enterprise',
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('purchased', false);
        }
    }

    public function test_no_purchase_event_when_fathom_is_off(): void
    {
        config(['seo.fathom_site_id' => null]);

        $this->get('/billing/success?checkout_id='.self::CHECKOUT.'&product=plus')
            ->assertOk()
            ->assertDontSee('Plus purchased', false);
    }

    public function test_polars_return_url_says_which_product_was_bought(): void
    {
        Http::fake([
            'sandbox-api.polar.sh/v1/checkouts/' => Http::response(['id' => 'chk_1', 'url' => 'https://sandbox.polar.sh/checkout/chk_1'], 201),
        ]);

        $workspace = app(TryTokenService::class)->create()['workspace'];
        $this->get(app(BillingService::class)->createCheckoutUrl($workspace, 'credits_50'))->assertRedirect();

        Http::assertSent(fn ($request) => str_ends_with($request['success_url'], '?checkout_id={CHECKOUT_ID}&product=credits_50'));
    }

    public function test_a_canceled_checkout_and_a_broken_one_each_say_so(): void
    {
        $this->get('/billing/cancel')->assertOk()->assertSee("fathom.trackEvent('Checkout canceled')", false);

        Http::fake(['sandbox-api.polar.sh/v1/checkouts/' => Http::response(['error' => 'x'], 500)]);
        $workspace = app(TryTokenService::class)->create()['workspace'];

        $this->get(app(BillingService::class)->createCheckoutUrl($workspace))
            ->assertStatus(503)
            ->assertSee("fathom.trackEvent('Checkout unavailable')", false);
    }

    public function test_the_pricing_section_reports_a_view_and_its_two_buttons(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("fathom.trackEvent('Pricing viewed')", false)
            ->assertSee("fathom.trackEvent('Pricing plus cta')", false)
            ->assertSee("fathom.trackEvent('Pricing credit costs')", false);
    }

    public function test_pageviews_skip_paths_that_carry_a_token_or_a_workspace_id(): void
    {
        $this->get('/billing/cancel')
            ->assertOk()
            ->assertSee('billing\\/(manage|checkout)', false);
    }
}
