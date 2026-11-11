---
name: review-prototypes
description: Compare working UI prototypes around a specific design decision. Use when choosing between interaction, layout, or flow variants and collecting evidence or feedback before implementation.
---

# Review UI prototypes

Frame one decision in terms of a user task. For example, compare a filter sheet and an inline filter panel by asking someone to narrow search results, then remove one filter while keeping the others. Agree on what will decide the comparison, such as finding the filters, seeing which ones are active, and returning to the results. If the options still need building, [Emil Kowalski’s Prototype skill](https://aiforui.dev/skills/prototype) can create working variants.

## Run the comparison

1. Give each option a short, stable name. Use the same test data, permissions, viewport, and starting state for each option within a scenario. Reset between trials. When asking several people to try the options, vary which option they see first where practical.
2. Try the real interaction. Record whether people can complete the task and where they hesitate or recover. For motion, gestures, or keyboard focus, include the trigger and observed behavior. If only screenshots are available, limit conclusions to what those images show. Distinguish what you observed from what reviewers told you.
3. Share a direct prototype link for each option when available. Ask reviewers to try the same task and name the option they would choose, one detail they would keep, and any issue that would change their choice.

## Collect feedback in ReviseMy

- Use `create_review` with `type: ui`. Send screenshots in `images` and the corresponding names in `image_details[].state`. Use up to five screenshots per review. If more than one viewport or state is included, make each name unambiguous, such as **Filter sheet · phone · filters applied**. Supply `dpr` and any element map from the same capture. Put the decision, test task, and available prototype links in the review context.
- Use `add_findings` for observed issues. Point each finding to the matching zero-based `screenshot_index` and include a region only when the issue has a visible location. Share the review link with the person who requested the comparison.
- Once feedback arrives, record the chosen option, the evidence for the decision, and any details to carry over. If the evidence is mixed, identify what another trial should resolve. When asked to verify an iteration, create a follow-up with `parent_id` and reuse the chosen variant’s capture names and viewport settings.

Without ReviseMy, return the prototype links, a short comparison by task, and the open decision in chat.
