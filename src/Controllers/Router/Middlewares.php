<?php

namespace EDGVI10\Controllers\Router;

/**
 * Middlewares prontos para uso com RouterController
 * 
 * Exemplos de uso:
 * 
 * // Middleware global
 * $router->addMiddleware(Middlewares::cors());
 * 
 * // Middleware em grupo
 * $router->group("/api", function($router) {
 *     // rotas aqui
 * }, [Middlewares::auth(), Middlewares::jsonOnly()]);
 * 
 * // Middleware customizado
 * $router->addMiddleware(function($request, $response, $params) {
 *     // sua lógica aqui
 *     return true; // continua para próxima rota
 * });
 */
class Middlewares
{
    /**
     * Middleware de autenticação via Bearer Token
     * 
     * @param callable $validator Função que recebe o token e retorna bool ou user data
     * @return callable
     */
    public static function auth($validator = null)
    {
        return function ($request, $response, $params) use ($validator) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

            if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $response->withError("Unauthorized: Token not provided", 401);
                return false;
            }

            $token = $matches[1];

            // Se não houver validador, apenas verifica se o token existe
            if (!$validator) {
                return true;
            }

            // Executa o validador customizado
            $result = call_user_func($validator, $token, $request, $response, $params);

            if ($result === false) {
                $response->withError("Unauthorized: Invalid token", 401);
                return false;
            }

            // Se o validador retornar dados do usuário, adiciona ao request
            if (is_array($result) || is_object($result)) {
                $request->user = $result;
            }

            return true;
        };
    }

    /**
     * Middleware de autenticação via API Key
     * 
     * @param string $headerName Nome do header que contém a API key
     * @param callable $validator Função que recebe a key e retorna bool
     * @return callable
     */
    public static function apiKey($headerName = 'X-API-Key', $validator = null)
    {
        return function ($request, $response, $params) use ($headerName, $validator) {
            $headers = getallheaders();
            $apiKey = $headers[$headerName] ?? null;

            if (!$apiKey) {
                $response->withError("Unauthorized: API Key not provided", 401);
                return false;
            }

            if ($validator) {
                $result = call_user_func($validator, $apiKey, $request);

                if ($result === false) {
                    $response->withError("Unauthorized: Invalid API Key", 401);
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * Middleware de CORS
     * 
     * @param array $config Configurações de CORS
     * @return callable
     */
    public static function cors($config = [])
    {
        $defaults = [
            'origin' => '*',
            'methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'headers' => 'Content-Type, Authorization, X-Requested-With, X-API-Key',
            'credentials' => false,
            'maxAge' => 86400,
        ];

        $config = array_merge($defaults, $config);

        return function ($request, $response, $params) use ($config) {
            $response->setHeader('Access-Control-Allow-Origin', $config['origin']);
            $response->setHeader('Access-Control-Allow-Methods', $config['methods']);
            $response->setHeader('Access-Control-Allow-Headers', $config['headers']);

            if ($config['credentials']) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            $response->setHeader('Access-Control-Max-Age', $config['maxAge']);

            // Se for OPTIONS, responde imediatamente
            if ($request->getMethod() === 'OPTIONS') {
                $response->withStatus(200);
                return false;
            }

            return true;
        };
    }

    /**
     * Middleware que aceita apenas requisições JSON
     * 
     * @return callable
     */
    public static function jsonOnly()
    {
        return function ($request, $response, $params) {
            $headers = getallheaders();
            $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

            if ($request->getMethod() !== 'GET' && strpos($contentType, 'application/json') === false) {
                $response->withError("Content-Type must be application/json", 415);
                return false;
            }

            return true;
        };
    }

    /**
     * Middleware de rate limiting simples
     * 
     * @param int $maxRequests Número máximo de requisições
     * @param int $windowSeconds Janela de tempo em segundos
     * @return callable
     */
    public static function rateLimit($maxRequests = 60, $windowSeconds = 60)
    {
        return function ($request, $response, $params) use ($maxRequests, $windowSeconds) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $cacheKey = "rate_limit_{$ip}";
            $cacheFile = sys_get_temp_dir() . "/{$cacheKey}.json";

            $data = [];
            if (file_exists($cacheFile)) {
                $content = file_get_contents($cacheFile);
                $data = json_decode($content, true);
            }

            $now = time();
            $data['requests'] = array_filter($data['requests'] ?? [], function ($timestamp) use ($now, $windowSeconds) {
                return ($now - $timestamp) < $windowSeconds;
            });

            if (count($data['requests']) >= $maxRequests) {
                $response->withError("Rate limit exceeded. Try again later.", 429);
                return false;
            }

            $data['requests'][] = $now;
            file_put_contents($cacheFile, json_encode($data));

            return true;
        };
    }

    /**
     * Middleware de logging de requisições
     * 
     * @param string $logPath Caminho do arquivo de log
     * @return callable
     */
    public static function logger($logPath = null)
    {
        $logPath = $logPath ?? __DIR__ . "/../../logs/requests.log";

        return function ($request, $response, $params) use ($logPath) {
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => $request->getMethod(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ];

            file_put_contents(
                $logPath,
                json_encode($logEntry) . PHP_EOL,
                FILE_APPEND
            );

            return true;
        };
    }

    /**
     * Middleware de tratamento de erros
     * 
     * @param callable $errorHandler Função customizada para tratar erros
     * @return callable
     */
    public static function errorHandler($errorHandler = null)
    {
        return function ($request, $response, $params) use ($errorHandler) {
            set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($response, $errorHandler) {
                if ($errorHandler) {
                    call_user_func($errorHandler, $errno, $errstr, $errfile, $errline, $response);
                } else {
                    $response->withError("Internal Server Error: {$errstr}", 500);
                }
            });

            set_exception_handler(function ($exception) use ($response, $errorHandler) {
                if ($errorHandler) {
                    call_user_func($errorHandler, $exception, $response);
                } else {
                    $response->withError("Exception: " . $exception->getMessage(), 500);
                }
            });

            return true;
        };
    }

    /**
     * Middleware de validação de campos obrigatórios
     * 
     * @param array $requiredFields Lista de campos obrigatórios
     * @param string $source Fonte dos dados: 'body', 'query', 'params'
     * @return callable
     */
    public static function validate($requiredFields = [], $source = 'body')
    {
        return function ($request, $response, $params) use ($requiredFields, $source) {
            $data = [];

            switch ($source) {
                case 'body':
                    $data = $request->getBody();
                    break;
                case 'query':
                    $data = $request->getQuery();
                    break;
                case 'params':
                    $data = $params;
                    break;
            }

            $missing = [];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                $response->withError(
                    "Missing required fields: " . implode(', ', $missing),
                    422
                );
                return false;
            }

            return true;
        };
    }

    /**
     * Middleware de cache simples
     * 
     * @param int $ttl Tempo de vida do cache em segundos
     * @return callable
     */
    public static function cache($ttl = 3600)
    {
        return function ($request, $response, $params) use ($ttl) {
            if ($request->getMethod() !== 'GET') {
                return true;
            }

            $cacheKey = md5($_SERVER['REQUEST_URI']);
            $cacheFile = sys_get_temp_dir() . "/cache_{$cacheKey}.json";

            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);

                if (time() - $data['timestamp'] < $ttl) {
                    $response->withJson($data['content']);
                    return false;
                }
            }

            // TODO: Implementar cache da resposta após execução da rota

            return true;
        };
    }
}
