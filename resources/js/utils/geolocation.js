/**
 * Browser geolocation helper.
 *
 * Wraps navigator.geolocation.getCurrentPosition in a Promise with a
 * normalised success shape and a structured rejection shape.
 */

/** Sensible defaults; caller options are merged over these. */
const DEFAULT_OPTIONS = {
    enableHighAccuracy: false,
    timeout: 10000,
    maximumAge: 300000, // 5 min — reuse a recent fix rather than re-request
};

/**
 * Map a browser GeolocationPositionError to our structured error shape.
 * Codes: 1 = PERMISSION_DENIED, 2 = POSITION_UNAVAILABLE, 3 = TIMEOUT.
 *
 * @param {GeolocationPositionError} error
 * @returns {{ code: 'denied' | 'unavailable' | 'timeout', message: string }}
 */
function mapError(error) {
    switch (error?.code) {
        case 1:
            return { code: 'denied', message: error.message || 'Location permission was denied.' };
        case 2:
            return { code: 'unavailable', message: error.message || 'Location information is unavailable.' };
        case 3:
            return { code: 'timeout', message: error.message || 'The location request timed out.' };
        default:
            return { code: 'unavailable', message: error?.message || 'An unknown geolocation error occurred.' };
    }
}

/**
 * Request the user's current position.
 *
 * @param {PositionOptions} [options] - Passed through to getCurrentPosition,
 *   merged over the defaults (enableHighAccuracy, timeout, maximumAge).
 * @returns {Promise<{ latitude: number, longitude: number }>} resolves with
 *   coordinates; rejects with { code: 'denied' | 'unavailable' | 'timeout', message }.
 */
export function getUserLocation(options = {}) {
    return new Promise((resolve, reject) => {
        if (!('geolocation' in navigator) || !navigator.geolocation) {
            reject({ code: 'unavailable', message: 'Geolocation is not supported by this browser.' });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                });
            },
            (error) => {
                reject(mapError(error));
            },
            { ...DEFAULT_OPTIONS, ...options },
        );
    });
}
