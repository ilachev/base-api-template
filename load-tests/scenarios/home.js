import http from 'k6/http';
import { sleep, check } from 'k6';
import { BASE_URL, DEFAULT_HEADERS, HIGH_LOAD_OPTIONS } from '../lib/config.js';

export const options = HIGH_LOAD_OPTIONS;

export default function () {
    // Создаем jar внутри функции виртуального пользователя, а не в контексте инициализации
    const jar = http.cookieJar();

    // Первый запрос
    const res = http.get(`${BASE_URL}/api/v1/home`, {
        headers: DEFAULT_HEADERS,
        cookies: jar // Передаем cookie jar для автоматического управления куками
    });

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 1000,
    });

    // К этому моменту jar уже должен содержать куки
    // Для последующих запросов используем тот же jar:
    // const res2 = http.get(`${BASE_URL}/api/v1/another-endpoint`, {
    //     headers: DEFAULT_HEADERS,
    //     cookies: jar
    // });

    sleep(1);
}