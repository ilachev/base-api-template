export const BASE_URL = 'http://localhost:8080';

export const DEFAULT_HEADERS = {
    'Authorization': '$EcR3t',
};

export const HIGH_LOAD_OPTIONS = {
    stages: [
        { duration: '30s', target: 100 },     // Плавный разогрев до 100 VUs
        { duration: '30s', target: 300 },     // Увеличение до 300 VUs
        { duration: '1m', target: 500 },      // Увеличение до 500 VUs
        { duration: '1m', target: 500 },      // Поддержание 500 VUs
        { duration: '30s', target: 0 },       // Плавное снижение
    ],
    thresholds: {
        http_req_duration: ['p(95)<1000'],   // Допустимое время ответа до 1 секунды
        http_req_failed: ['rate<0.05'],      // Допускаем до 5% ошибок
    },
};
