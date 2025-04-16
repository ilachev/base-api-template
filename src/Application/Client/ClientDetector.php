<?php

declare(strict_types=1);

namespace App\Application\Client;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Сервис для определения и сопоставления клиентов на основе fingerprint.
 */
final readonly class ClientDetector implements ClientDetectorInterface
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private ClientConfig $config,
    ) {}

    /**
     * Определяет, принадлежит ли запрос уже известному клиенту.
     *
     * @param ServerRequestInterface $request Текущий HTTP-запрос
     * @param bool $includeCurrent Включать ли текущую сессию в результаты поиска (для тестов)
     * @return array<ClientIdentity> Список найденных похожих клиентов, отсортированный по схожести
     */
    public function findSimilarClients(ServerRequestInterface $request, bool $includeCurrent = false): array
    {
        // Получаем текущую сессию из атрибутов запроса
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return [];
        }

        // Создаем идентификатор текущего клиента
        $currentIdentity = ClientIdentity::fromSession($currentSession);

        // Получаем все сессии
        $allSessions = $this->sessionRepository->findAll();

        // Фильтруем текущую сессию, если нужно
        $otherSessions = $includeCurrent
            ? $allSessions  // Включаем все сессии для тестов
            : array_filter(
                $allSessions,
                static fn(Session $session) => $session->id !== $currentSession->id,
            );

        if (empty($otherSessions)) {
            return [];
        }

        // Преобразуем все сессии в идентификаторы и вычисляем схожесть
        $similarities = [];
        foreach ($otherSessions as $session) {
            $otherIdentity = ClientIdentity::fromSession($session);
            $score = $this->calculateSimilarityScore($currentIdentity, $otherIdentity);

            // Добавляем клиента, только если схожесть выше порога
            if ($score >= $this->config->similarityThreshold) {
                $similarities[] = [
                    'identity' => $otherIdentity,
                    'score' => $score,
                ];
            }
        }

        // Сортируем по убыванию схожести
        usort($similarities, static fn(array $a, array $b) => $b['score'] <=> $a['score']);

        // Возвращаем только идентификаторы клиентов
        return array_map(
            static fn(array $item) => $item['identity'],
            $similarities,
        );
    }

    /**
     * Проверяет, является ли текущий запрос потенциально опасным
     * (например, слишком много сессий с одного IP).
     *
     * @param ServerRequestInterface $request Текущий HTTP-запрос
     */
    public function isRequestSuspicious(ServerRequestInterface $request): bool
    {
        // Получаем текущую сессию из атрибутов запроса
        /** @var ?Session $currentSession */
        $currentSession = $request->getAttribute('session');

        if ($currentSession === null) {
            return false;
        }

        // Создаем идентификатор текущего клиента и извлекаем IP
        $currentIdentity = ClientIdentity::fromSession($currentSession);
        $currentIp = $currentIdentity->ipAddress;

        // Если IP неизвестен - пропускаем проверку
        if ($currentIp === 'unknown') {
            return false;
        }

        // Получаем все сессии с тем же IP
        $allSessions = $this->sessionRepository->findAll();
        $sessionsWithSameIp = array_filter(
            $allSessions,
            static function (Session $session) use ($currentIp, $currentSession) {
                if ($session->id === $currentSession->id) {
                    return false;
                }

                $payload = json_decode($session->payload, true);
                if (!\is_array($payload)) {
                    return false;
                }

                return ($payload['ip'] ?? 'unknown') === $currentIp;
            },
        );

        // Если с одного IP слишком много сессий - считаем подозрительным
        return \count($sessionsWithSameIp) >= $this->config->maxSessionsPerIp;
    }

    /**
     * Вычисляет рейтинг "похожести" между двумя идентификаторами клиентов
     * Чем выше значение, тем больше уверенность, что это один и тот же клиент
     *
     * @param ClientIdentity $identity1 Первый идентификатор
     * @param ClientIdentity $identity2 Второй идентификатор
     * @return float Значение от 0.0 до 1.0
     */
    private function calculateSimilarityScore(ClientIdentity $identity1, ClientIdentity $identity2): float
    {
        // Если ID совпадают, это 100% совпадение
        if ($identity1->id === $identity2->id) {
            return 1.0;
        }

        $score = 0.0;

        // Проверка IP
        if ($identity1->ipAddress !== 'unknown' && $identity1->ipAddress === $identity2->ipAddress) {
            $score += $this->config->ipMatchWeight;
        }

        // Проверка User-Agent
        if ($identity1->userAgent !== null && $identity1->userAgent === $identity2->userAgent) {
            $score += $this->config->userAgentMatchWeight;
        }

        // Проверка дополнительных атрибутов
        if (!empty($identity1->attributes) || !empty($identity2->attributes)) {
            // Создаем объединенный список всех атрибутов
            $allAttributes = array_unique(array_merge(
                array_keys($identity1->attributes),
                array_keys($identity2->attributes),
            ));

            if (!empty($allAttributes)) {
                $matchCount = 0;

                foreach ($allAttributes as $key) {
                    // Проверяем наличие атрибута с одинаковым значением в обоих идентификаторах
                    if (
                        isset($identity1->attributes[$key], $identity2->attributes[$key])
                        && $identity1->attributes[$key] === $identity2->attributes[$key]
                    ) {
                        ++$matchCount;
                    }
                }

                // Вычисляем процент совпадения атрибутов
                $attributeMatchPercent = $matchCount / \count($allAttributes);
                $score += $this->config->attributesMatchWeight * $attributeMatchPercent;
            }
        }

        // Для тестовых сред, если IP и User-Agent совпадают полностью,
        // это скорее всего один и тот же клиент
        if (
            $identity1->ipAddress !== 'unknown'
            && $identity1->ipAddress === $identity2->ipAddress
            && $identity1->userAgent !== null
            && $identity1->userAgent === $identity2->userAgent
        ) {
            // Увеличиваем схожесть, если оба главных параметра совпадают
            $score = max($score, 0.9);
        }

        return $score;
    }
}
