<?php

use App\Models\Annotation;
use App\Models\Finding;

/*
 * Fictional reviews the marketing pages draw with the review page's own
 * pieces (<x-review-mock>). One per review type. Marks sit by fraction of the
 * shot, like real ones: x/y for a point, plus w/h for a region. Severity and
 * status are Annotation constants, so labels and colours come from the model.
 * `context` is the What to look at line. The page under the marks is review-mock/samples/{key}.blade.php.
 */
return [
    'website' => [
        'title' => 'Fieldnote Coffee home page',
        'context' => 'New hero and product cards before Friday’s launch.',
        'source' => 'fieldnote.coffee',
        'captured' => 'captured 2 minutes ago',
        'pass' => 1,
        'label' => 'A ReviseMy review of a coffee roaster’s home page, with three marks and two hints',
        'marks' => [
            ['x' => 0.07, 'y' => 0.17, 'w' => 0.5, 'h' => 0.23, 'severity' => Annotation::SEVERITY_MUST_FIX, 'status' => Annotation::STATUS_OPEN, 'note' => 'Headline says what we are, not what you get. Lead with the subscription.'],
            ['x' => 0.215, 'y' => 0.475, 'severity' => Annotation::SEVERITY_NIT, 'status' => Annotation::STATUS_OPEN, 'note' => 'The button gets lost next to the link. Give it more weight.'],
            ['x' => 0.06, 'y' => 0.63, 'w' => 0.88, 'h' => 0.34, 'severity' => Annotation::SEVERITY_KEEP, 'status' => Annotation::STATUS_OPEN, 'note' => 'The product cards. Keep the photos and the prices this size.'],
        ],
        'hints' => [
            ['x' => 0.84, 'y' => 0.09, 'severity' => Finding::SEVERITY_SUGGESTION, 'text' => 'Nav has five links and no clear current page.'],
            ['x' => 0.66, 'y' => 0.36, 'severity' => Finding::SEVERITY_A11Y, 'text' => 'Light grey body text over the photo may miss AA contrast.'],
        ],
    ],

    'ui' => [
        'title' => 'Ledgerly dashboard, empty month',
        'context' => 'Tiles reworked from your marks. Check the fixes.',
        'source' => 'Screenshot',
        'pass' => 2,
        'label' => 'A ReviseMy review of a finance dashboard on its second pass, with marks the agent has fixed',
        'marks' => [
            ['x' => 0.27, 'y' => 0.16, 'w' => 0.7, 'h' => 0.2, 'severity' => Annotation::SEVERITY_MUST_FIX, 'status' => Annotation::STATUS_RESOLVED, 'note' => 'Three stat tiles fight for attention. Make balance the big one.'],
            ['x' => 0.62, 'y' => 0.55, 'severity' => Annotation::SEVERITY_QUESTION, 'status' => Annotation::STATUS_OPEN, 'note' => 'Should pending payments show here, or only cleared ones?'],
            ['x' => 0.03, 'y' => 0.05, 'w' => 0.2, 'h' => 0.5, 'severity' => Annotation::SEVERITY_NIT, 'status' => Annotation::STATUS_VERIFIED, 'note' => 'Sidebar icons and labels don’t line up.'],
        ],
        'hints' => [
            ['x' => 0.9, 'y' => 0.85, 'severity' => Finding::SEVERITY_POLISH, 'text' => 'Amounts aren’t tabular, so the column wobbles.'],
        ],
    ],

    'email' => [
        'title' => 'October newsletter',
        'context' => 'Subject, preview text and the button. Goes out Tuesday.',
        'source' => 'HTML email',
        'pass' => 1,
        'label' => 'A ReviseMy review of a newsletter, with marks on the subject line and the call to action',
        'marks' => [
            ['x' => 0.05, 'y' => 0.04, 'w' => 0.9, 'h' => 0.12, 'severity' => Annotation::SEVERITY_MUST_FIX, 'status' => Annotation::STATUS_OPEN, 'note' => 'Preview text repeats the subject. Use it to say what’s inside.'],
            ['x' => 0.5, 'y' => 0.78, 'severity' => Annotation::SEVERITY_NIT, 'status' => Annotation::STATUS_OPEN, 'note' => 'Button text is vague. Try “Pick your beans”.'],
        ],
        'hints' => [
            ['x' => 0.5, 'y' => 0.42, 'severity' => Finding::SEVERITY_A11Y, 'text' => 'The banner image has no alt text, so blocked images read as blank.'],
        ],
    ],

    'presentation' => [
        'title' => 'Seed deck, slide 4',
        'context' => 'Traction slide. Does the headline land in five seconds?',
        'source' => 'PDF',
        'pass' => 1,
        'label' => 'A ReviseMy review of a pitch deck slide, with a mark on the chart and one on the headline',
        'marks' => [
            ['x' => 0.07, 'y' => 0.1, 'w' => 0.6, 'h' => 0.18, 'severity' => Annotation::SEVERITY_MUST_FIX, 'status' => Annotation::STATUS_OPEN, 'note' => 'Say the number in the headline. “3× retention since March.”'],
            ['x' => 0.9, 'y' => 0.5, 'severity' => Annotation::SEVERITY_KEEP, 'status' => Annotation::STATUS_OPEN, 'note' => 'One accent bar on the chart. Keep it that quiet.'],
        ],
        'hints' => [
            ['x' => 0.3, 'y' => 0.88, 'severity' => Finding::SEVERITY_POLISH, 'text' => 'Footnote is under 10pt at projector size.'],
        ],
    ],
];
