// Single source of truth for the player animal avatars (JS side).
// Keep in lockstep with the PHP enum App\Enums\Avatar (same keys, same
// order). Emoji are written as \u{...} escapes because a previous tool pass
// normalised raw non-ASCII characters in this repo — escapes survive that.
export const AVATARS = [
    { key: 'fox', emoji: '\u{1F98A}', label: 'Liška' },
    { key: 'hedgehog', emoji: '\u{1F994}', label: 'Ježek' },
    { key: 'owl', emoji: '\u{1F989}', label: 'Sova' },
    { key: 'rabbit', emoji: '\u{1F430}', label: 'Zajíc' },
    { key: 'bear', emoji: '\u{1F43B}', label: 'Medvěd' },
    { key: 'frog', emoji: '\u{1F438}', label: 'Žába' },
    { key: 'squirrel', emoji: '\u{1F43F}\u{FE0F}', label: 'Veverka' },
    { key: 'deer', emoji: '\u{1F98C}', label: 'Jelen' },
];

const FALLBACK_EMOJI = '\u{1F642}';

export function avatarEmoji(key) {
    return AVATARS.find((avatar) => avatar.key === key)?.emoji ?? FALLBACK_EMOJI;
}
