// Suggest a type only from explicit wording. The reviewer can override it.
window.rmSuggestMarkType = (body) => {
    const text = body.trim();
    if (!text) return 'must-fix';

    if (/^(?:please\s+)?(?:keep\s+(?:this|it|the)\b|leave\s+.+\s+(?:alone|as is)\b)|^(?:don['’]t|do not)\s+change\b/i.test(text)) return 'keep';
    if (/^(?:must\b|blocker\b|critical\b|required\b)|\b(?:must fix|blocking|broken)\b/i.test(text)) return 'must-fix';
    if (/^(?:optional\b|nit\b|nice to have\b|consider\b)|\bif there(?:['’]s| is) time\b/i.test(text)) return 'nit';
    if (/\?$/.test(text) || /^(?:question\b|why\b|what\b|how\b|(?:can|could|should|would)\s+(?:we|you|this|it)\b)/i.test(text)) return 'question';

    return 'must-fix';
};
