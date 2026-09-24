export const TASK_TYPE_LABELS = {
    arrival_only: 'Jen dojít',
    pick_image: 'Vybrat obrázek',
    photo_of_something: 'Vyfotit něco',
    selfie_doing_something: 'Vyfotit se při činnosti',
    password: 'Zadat heslo',
};

export function taskTypeLabel(type) {
    return TASK_TYPE_LABELS[type] ?? type;
}

// position is the 1-based place in the ordered list.
export function locationLabel(location, position) {
    return `#${position} ${location.name || `Stanoviště ${position}`}`;
}
