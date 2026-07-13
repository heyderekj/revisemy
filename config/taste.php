<?php

/**
 * Second-opinion craft lenses by review type.
 *
 * Findings never credit people as authors of a hint. Disclosure lives on the
 * review "craft" chip and the /second-opinion marketing page. Bodies and
 * prompts are ReviseMy-distilled public principles — not quotes or endorsements.
 */

return [

    'disclaimer' => 'ReviseMy-distilled heuristics for this review type — not affiliated with, endorsed by, or quoting these people or organizations on your review.',

    'lenses' => [

        'iids' => [
            'id' => 'iids',
            'name' => 'IIDS',
            'blurb' => 'Typography hierarchy, grids and alignment, proportion, precision, and restraint — International Interface Design Style.',
            'source_url' => 'https://shiftnudge.com/iids',
            'source_label' => 'shiftnudge.com/iids',
        ],

        'laws_of_ux' => [
            'id' => 'laws_of_ux',
            'name' => 'Laws of UX',
            'blurb' => 'Psychology heuristics visible in a still — aesthetic-usability, simplicity (Prägnanz), similarity, choice density, and target size.',
            'source_url' => 'https://lawsofux.com/',
            'source_label' => 'lawsofux.com',
        ],

        'a_list_apart' => [
            'id' => 'a_list_apart',
            'name' => 'A List Apart',
            'blurb' => 'Web-native craft for a still — content hierarchy, responsive layout clarity, web typography, and progressive enhancement.',
            'source_url' => 'https://alistapart.com/',
            'source_label' => 'alistapart.com',
        ],

        'presentation_zen' => [
            'id' => 'presentation_zen',
            'name' => 'Presentation Zen',
            'blurb' => 'Design for the back of the room, use emptiness as design, and strip non-essentials.',
            'source_url' => 'https://www.garrreynolds.com/design-tips',
            'source_label' => 'garrreynolds.com/design-tips',
        ],

        'slide_craft' => [
            'id' => 'slide_craft',
            'name' => 'Slide craft',
            'blurb' => 'One idea per slide, glance-media clarity (~3 seconds), and contrast that amplifies the point instead of camouflaging it.',
            'source_url' => 'https://www.duarte.com/blog/perfect-your-slide-design/',
            'source_label' => 'Duarte: slide design',
        ],

        'good_email_code' => [
            'id' => 'good_email_code',
            'name' => 'Good Email Code',
            'blurb' => 'Live text over image-baked copy, accessibility first, progressive enhancement, and client-resilient layout.',
            'source_url' => 'https://www.goodemailcode.com/',
            'source_label' => 'goodemailcode.com',
        ],

    ],

    'types' => [

        'ui' => [
            'label' => 'UI craft',
            'lenses' => ['iids', 'laws_of_ux'],
        ],

        'website' => [
            'label' => 'Website craft',
            'lenses' => ['iids', 'a_list_apart'],
        ],

        'presentation' => [
            'label' => 'Slide craft',
            'lenses' => ['presentation_zen', 'slide_craft'],
        ],

        'email' => [
            'label' => 'Email craft',
            'lenses' => ['good_email_code'],
        ],

    ],

];
