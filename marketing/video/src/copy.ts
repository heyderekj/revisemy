// Every word on screen, in one place. The Fieldnote strings match the site's
// sample review (config/review-samples.php, hero-loop-preview) word for word.
// Spoken lines live in src/vo/script.json.

export const copy = {
  hook: {
    title: 'Visual feedback',
    hand: 'your agent.',
    agents: ['ChatGPT', 'Claude', 'Copilot', 'Cursor', 'Grok'],
  },

  chat: {
    placeholder: 'Ask your agent anything…',
    userAsk: 'Check the new home page before we ship.',
    createTool: 'create_review',
    createArgs: 'capture_url: "fieldnote.coffee"',
    steps: ['Captured desktop', 'Captured mobile', 'Ran the checklist'],
    reviewReady: 'Captured fieldnote.coffee on desktop and mobile and opened a review. Mark what matters, and I’ll pick it up from there.',
    reviewUrl: 'revisemy.com/r/k7Q2xb',
  },

  review: {
    noAccount: 'no account needed',
    m1: { severity: 'Must fix', note: 'Headline says what we are, not what you get. Lead with the subscription.' },
    m2: { severity: 'Nice to have', note: 'The button gets lost next to the link. Give it more weight.' },
    m3: { severity: 'Keep this', note: 'The product cards. Keep the photos and the prices this size.' },
    s1: { kind: 'Suggestion', note: 'Nav has five links and no clear current page.' },
    s2: { kind: 'Accessibility', note: 'Light grey body text over the photo may miss AA contrast.' },
    g1: { who: 'Priya', role: 'client', note: 'Could the mug have our logo on it?' },
    shared: 'Guest link copied',
    added: 'Added M4',
    requested: 'Changes requested. 4 marks sent to your agent.',
  },

  fix: {
    notified: 'Changes requested on Pass 1',
    getTool: 'get_review',
    nextAction: 'apply_pins_then_next_pass',
    plan: [
      { id: 'M1', kind: 'mark', intent: 'Must fix', text: 'Rewrite the headline around the subscription' },
      { id: 'M2', kind: 'mark', intent: 'Nice to have', text: 'Make the button solid so it leads' },
      { id: 'M4', kind: 'guest', intent: 'Nice to have', text: 'Put the Fieldnote logo on the mug' },
      { id: 'M3', kind: 'mark', intent: 'Keep this', text: 'Leave the product cards as they are' },
    ],
    resolveTool: 'resolve_marks',
    resolveArgs: 'M1, M2, M4 · resolved · after_image ✓',
    ready: 'Pass 2 is ready. Each fix has a before and after for you to check.',
  },

  verify: {
    approved: 'Looks good — approved',
  },

  audience: {
    kicker: 'Built for everyone in the loop',
    cells: [
      { n: '01', who: 'Agencies', line: 'You run the agent. Clients mark on the link.' },
      { n: '02', who: 'Freelancers', line: 'No more screenshot ping-pong over email.' },
      { n: '03', who: 'Teams', line: 'Designers mark. Agents ship. Everyone verifies.' },
    ],
    sources: ['Screenshots', 'URLs', 'PDFs', 'Email HTML'],
  },

  cta: {
    tagline: 'Visual feedback for your agent.',
    free: 'Free to try',
    url: 'revisemy.com',
  },
};
