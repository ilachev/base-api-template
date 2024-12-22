export const BASE_URL = 'http://localhost:8080';

export const DEFAULT_HEADERS = {
    'Authorization': '$EcR3t',
};

export const HIGH_LOAD_OPTIONS = {
    stages: [
        { duration: '30s', target: 10000 },    // Быстрый разогрев до 100 VUs
        { duration: '1m', target: 10000 },     // Поддержание 100 VUs
        { duration: '30s', target: 0 },    // Увеличение до 200 VUs
        // { duration: '1m', target: 200 },     // Поддержание 200 VUs
        // { duration: '30s', target: 300 },    // Пиковая нагрузка 300 VUs
        // { duration: '1m', target: 300 },     // Поддержание пиковой нагрузки
        // { duration: '30s', target: 0 },      // Плавное снижение
    ],
    thresholds: {
        http_req_duration: ['p(95)<1000'],   // Увеличиваем допустимое время ответа до 1 секунды
        http_req_failed: ['rate<0.05'],      // Допускаем до 5% ошибок
    },
};
