import http from 'k6/http';
import { sleep, check } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, DEFAULT_OPTIONS } from '../lib/config.js';

export const options = DEFAULT_OPTIONS;

export default function () {
    const res = http.get(`${BASE_URL}/home`, { headers: DEFAULT_HEADERS });

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1);
}