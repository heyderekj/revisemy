---
name: review-product-flow
description: Review an existing product flow across its meaningful states, such as onboarding, checkout, validation, empty states, or confirmation. Use when asked for design feedback on a task that spans screens or changes as someone interacts with it.
---

# Review a product flow

Pick one user task and the viewport that matters most. Run the actual flow in a browser using demo data and an existing session where needed. Use a test environment for actions that submit orders, send messages, or change real records. Stop at a safe preview when you cannot exercise the last step safely, and say which states you could not verify.

## Capture and critique

1. Capture up to five states that change the user’s decision or next action. For checkout, that might be **Checkout ready**, **Card declined**, and **Order confirmed**. Use the same viewport throughout. Give each state a name you can reuse on the next pass.
2. On each state, ask what the user knows, what they can do next, and how they can recover. Check that form errors identify the field and explain the fix, async actions give feedback without losing context, and success says what happened. Test keyboard and focus behavior in the browser. A still image cannot establish motion quality, keyboard behavior, or accessibility.
3. Use an installed specialist skill if it fits the task. For example, use Jakub Krehel’s `better-writing` to review errors and labels, or Emil Kowalski’s `review-animations` for motion you can replay. Run the review portion and attribute that lens. The review can proceed without either skill.
4. Keep up to five findings ordered by what prevents or delays completion. Each needs the state name, an observation, the consequence, and a specific change. Mention missing evidence instead of treating absent or partially loaded content as a flaw.

## Share it

- With ReviseMy connected, use `create_review` with `type: ui`, screenshots in `images`, and names in `image_details[].state` in the same order. Include the actual viewport and task in the context. Include `dpr` and an element map when collected from the same state as the shot. See [named state captures](https://revisemy.com/docs/rest-api#element-maps).
- Use `add_findings` for suggestions, with a zero-based `screenshot_index` matching the state. Add an area only if the finding can be placed on those pixels. Include the trigger and observed result in the finding for issues that only show up in motion or with a keyboard.
- Share the review link and let the person decide what becomes work. If they ask for changes, follow the normal review loop. For the next pass, capture the same named states at the same viewport so the fix can be compared.
- Without ReviseMy, return the state-by-state findings in chat. A review request alone does not authorize editing the product.
