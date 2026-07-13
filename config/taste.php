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

        'design_engineering' => [
            'id' => 'design_engineering',
            'name' => 'Design engineering',
            'blurb' => 'Press feedback, soft depth on floating surfaces, and restrained motion (ease-out, skip keyboard-triggered chrome).',
            'source_url' => 'https://animations.dev/',
            'source_label' => 'animations.dev',
        ],

        'iids' => [
            'id' => 'iids',
            'name' => 'IIDS',
            'blurb' => 'Typography hierarchy, grids and alignment, proportion, precision, and restraint — International Interface Design Style.',
            'source_url' => 'https://shiftnudge.com/iids',
            'source_label' => 'shiftnudge.com/iids',
        ],

        'fluid_interfaces' => [
            'id' => 'fluid_interfaces',
            'name' => 'Fluid interfaces',
            'blurb' => 'Respond on press, continuous feedback during interaction, and interruptible motion when gesture UI is implied.',
            'source_url' => 'https://developer.apple.com/videos/play/wwdc2018/803/',
            'source_label' => 'WWDC: Designing Fluid Interfaces',
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
            'lenses' => ['design_engineering', 'iids', 'fluid_interfaces'],
        ],

        'website' => [
            'label' => 'Website craft',
            'lenses' => ['iids', 'design_engineering'],
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
