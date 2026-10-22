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
    { key: 'wolf', emoji: '\u{1F43A}', label: 'Vlk' },
    { key: 'boar', emoji: '\u{1F417}', label: 'Divočák' },
    { key: 'badger', emoji: '\u{1F9A1}', label: 'Jezevec' },
    { key: 'mouse', emoji: '\u{1F42D}', label: 'Myška' },
    { key: 'bat', emoji: '\u{1F987}', label: 'Netopýr' },
    { key: 'duck', emoji: '\u{1F986}', label: 'Kachna' },
    { key: 'turtle', emoji: '\u{1F422}', label: 'Želva' },
    { key: 'snail', emoji: '\u{1F40C}', label: 'Šnek' },
    { key: 'ladybug', emoji: '\u{1F41E}', label: 'Beruška' },
    { key: 'bee', emoji: '\u{1F41D}', label: 'Včelka' },
    { key: 'butterfly', emoji: '\u{1F98B}', label: 'Motýl' },
    { key: 'lion', emoji: '\u{1F981}', label: 'Lev' },
];

const FALLBACK_EMOJI = '\u{1F642}';

export function avatarEmoji(key) {
    return AVATARS.find((avatar) => avatar.key === key)?.emoji ?? FALLBACK_EMOJI;
}
