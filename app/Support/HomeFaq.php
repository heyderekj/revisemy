<?php

namespace App\Support;

/**
 * The homepage questions, once: the page draws them, and the FAQ structured
 * data and the markdown twin read the same answers as plain text.
 */
final class HomeFaq
{
    /**
     * Answers may carry links, so they are HTML.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function all(): array
    {
        $pricing = (bool) config('billing.pricing_enabled');
        $credits = (int) config('billing.plans.free.credits', 20);

        $faq = [
            ['Do I need to sign up?', 'No. Pick your assistant under <a href="/#setup" class="link">Connect</a>. Most connect by pasting one address and clicking Connect, which makes a try workspace that’s yours.'.($pricing ? '' : " {$credits} credits a month, free for now.")],
            ['Where does the review open?', 'Always at a review link you can open anywhere. In Claude and VS Code it can also open right in the chat, so you mark and decide without leaving it.'],
            ['My marks, second opinion, guests — who’s in charge?', 'You. Your marks are the brief the agent works from. Second opinion and guest notes stay suggestions until you accept them.'],
            ['What happens when I run out of credits?', $pricing
                ? 'New checkups pause until your monthly credits refill, or top up right away with a credit pack (never expires) or Plus. Monthly credits don’t roll over. Your agent can check with get_billing.'
                : "New checkups pause until your {$credits} credits refill next month. Screenshots and PDFs cost 1, email HTML 3, a live URL 5. Your agent can check the date with get_billing."],
            ['What’s a pass, and the board?', 'The board is your checklist: each mark goes open, resolved, verified. When you ask for changes, your agent sends fresh captures as the next pass, and you check what it fixed. See <a href="/board" class="link">the board</a>.'],
            ['Can someone else look too?', 'Yes. Share a guest link from the review: they can suggest, and you decide what becomes a mark. See <a href="/guest-links" class="link">guest links</a>.'],
        ];

        if ($pricing) {
            $faq[] = ['How do I upgrade or cancel?', 'Ask your agent: create_checkout opens a Polar checkout for Plus or a one-time credit pack, create_portal handles cards and receipts, and cancel_subscription ends Plus at the close of the period.'];
        }

        return $faq;
    }

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function plain(): array
    {
        return array_map(fn (array $item) => ['q' => $item[0], 'a' => trim(strip_tags($item[1]))], self::all());
    }
}
