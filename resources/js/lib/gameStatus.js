const LABELS = { draft: 'koncept', published: 'publikováno', archived: 'archivováno' };
const TONES = { draft: 'slate', published: 'green', archived: 'amber' };

export const gameStatusLabel = (status) => LABELS[status] ?? status;
export const gameStatusTone = (status) => TONES[status] ?? 'slate';
