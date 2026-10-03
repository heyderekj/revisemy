// Every word on screen, in one place. Edit and re-render.

export const copy = {
  hook: {
    title: 'Visual feedback',
    hand: 'your agent.',
    agents: ['ChatGPT', 'Claude', 'Copilot', 'Cursor', 'Grok'],
  },

  chat: {
    title: 'New chat',
    connector: 'ReviseMy',
    placeholder: 'Ask your agent anything…',
    userAsk: 'Run a design checkup on the Northwind homepage before the client sees it.',
    agentAck: 'On it. Capturing the page and opening a review.',
    createTool: 'create_review',
    createArgs: 'capture_url: "northwind.studio"',
    steps: ['Capturing desktop', 'Capturing mobile', 'Running checklist'],
    reviewReady: 'Review is ready. Mark anything that needs work, then approve or request changes.',
    reviewTitle: 'Northwind homepage',
    reviewUrl: 'revisemy.com/r/k7Q2xb',
    openReview: 'Open review',
  },

  review: {
    pass: 'Pass 1',
    source: 'northwind.studio',
    title: 'Northwind homepage',
    noAccount: 'No account needed',
    m1: { note: 'Headline feels cramped. Give it room to breathe.', severity: 'Must fix' },
    m2: { note: 'CTA copy', suggested: 'Book a call', severity: 'Nice to have' },
    s1: { note: 'Button contrast is 3.9:1. Aim for 4.5:1.', label: 'Second opinion' },
    g1: { who: 'Priya', role: 'client', note: 'Love the photo. Can it be warmer?' },
    requestChanges: 'Request changes',
  },

  fix: {
    notified: 'Changes requested on Pass 1 · 4 marks',
    getTool: 'get_review',
    nextAction: 'apply_pins_then_next_pass',
    working: 'Applying your marks:',
    items: [
      { id: 'M1', text: 'Loosen headline spacing' },
      { id: 'M2', text: 'CTA → “Book a call”' },
      { id: 'S1', text: 'Darken button to 4.6:1' },
      { id: 'G1', text: 'Warm up the hero photo' },
    ],
    resolveTool: 'resolve_marks',
    done: 'Pass 2 is ready for your eye.',
  },

  board: {
    columns: ['Open', 'In progress', 'Resolved', 'Verified'],
    verifyAll: 'Verify all',
    approve: 'Approve',
    approved: 'Looks good — approved',
    tagline: 'Humans mark and approve. Agents ship the next pass.',
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
    tagline: 'Mark feedback for your agent.',
    free: 'Free to try',
    url: 'revisemy.com',
    works: 'Works with ChatGPT · Claude · Copilot · Cursor · Grok',
  },
};
