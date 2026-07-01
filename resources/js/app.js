import './bootstrap';

// Geolocation utility (wired into components in a later step). Exposed on
// window so the bundler retains it and it's reachable when we wire it up.
import { getUserLocation } from './utils/geolocation';

window.getUserLocation = getUserLocation;
