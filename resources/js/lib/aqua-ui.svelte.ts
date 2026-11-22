const WIRE_KEY = 'aqua-wire';

function storedWire(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(WIRE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * State ringan untuk tampilan SmartAqua (toast, modal bantuan, mode wire).
 * Dipakai lintas halaman lewat import biasa.
 */
export const aquaUi = $state({
    wire: storedWire(),
    helpOpen: false,
    toast: 'Tersimpan',
    toastVisible: false,
});

let toastTimer: ReturnType<typeof setTimeout> | null = null;

export function say(message: string): void {
    aquaUi.toast = message;
    aquaUi.toastVisible = true;

    if (toastTimer) {
        clearTimeout(toastTimer);
    }

    toastTimer = setTimeout(() => {
        aquaUi.toastVisible = false;
    }, 2800);
}

export function toggleWire(): void {
    aquaUi.wire = !aquaUi.wire;

    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(WIRE_KEY, aquaUi.wire ? '1' : '0');
    } catch {
        // localStorage bisa diblokir; abaikan saja.
    }
}

export function openHelp(): void {
    aquaUi.helpOpen = true;
}

export function closeHelp(): void {
    aquaUi.helpOpen = false;
}
