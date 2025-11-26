# Router Controller

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Version](https://img.shields.io/badge/version-1.0.1-orange)

Uma biblioteca PHP moderna e leve para roteamento de requisições HTTP, com suporte a middlewares, grupos de rotas e validações integradas.

## 📋 Índice

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalação](#-instalação)
- [Configuração do Servidor](#-configuração-do-servidor)
  - [Apache (.htaccess)](#apache-htaccess)
  - [Nginx](#nginx)
- [Uso Básico](#-uso-básico)
- [Rotas](#-rotas)
- [Grupos de Rotas](#-grupos-de-rotas)
- [Middlewares](#-middlewares)
- [Request](#-request)
- [Response](#-response)
- [Exemplos Práticos](#-exemplos-práticos)
- [Licença](#-licença)

## ✨ Características

- ✅ Roteamento RESTful completo (GET, POST, PUT, PATCH, DELETE, OPTIONS)
- ✅ Suporte a parâmetros dinâmicos nas rotas
- ✅ Sistema de middlewares global e por rota
- ✅ Grupos de rotas com prefixos e middlewares compartilhados
- ✅ Request e Response objects com métodos úteis
- ✅ Middlewares prontos (CORS, Auth, Rate Limit, etc.)
- ✅ Suporte a JSON e HTML
- ✅ Sistema de logs de erros
- ✅ Modo debug para desenvolvimento

## 📦 Requisitos

- PHP >= 7.4
- Composer

## 🚀 Instalação

Instale via Composer:

```bash
composer require edgvi10/router
```

Ou adicione ao seu `composer.json`:

```json
{
    "require": {
        "edgvi10/router": "^1.0"
    }
}
```

## ⚙️ Configuração do Servidor

### Apache (.htaccess)

Crie um arquivo `.htaccess` na raiz do projeto:

```apache
# Habilita o módulo de reescrita
RewriteEngine On

# Redireciona para HTTPS (opcional)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Remove trailing slashes
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} (.+)/$
RewriteRule ^ %1 [L,R=301]

# Redireciona tudo para index.php exceto arquivos e diretórios reais
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Proteção de arquivos sensíveis
<FilesMatch "^(composer\.json|composer\.lock|\.env|\.git)">
    Order allow,deny
    Deny from all
</FilesMatch>

# Configurações de segurança
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"

# Configurações de cache (ajuste conforme necessário)
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/json "access plus 0 seconds"
</IfModule>
```

### Nginx

Adicione ao seu arquivo de configuração do Nginx:

```nginx
server {
    listen 80;
    listen [::]:80;
    
    server_name seu-dominio.com;
    root /var/www/html;
    index index.php;

    # Redireciona para HTTPS (opcional)
    # return 301 https://$server_name$request_uri;

    # Charset
    charset utf-8;

    # Logs
    access_log /var/log/nginx/seu-projeto-access.log;
    error_log /var/log/nginx/seu-projeto-error.log;

    # Remove trailing slashes
    rewrite ^/(.*)/$ /$1 permanent;

    # Configuração principal
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Processa arquivos PHP
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; # Ajuste para sua versão do PHP
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeouts
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Nega acesso a arquivos sensíveis
    location ~ /\.(ht|git|env) {
        deny all;
    }

    location ~ /composer\.(json|lock)$ {
        deny all;
    }

    # Headers de segurança
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Desabilita logs para arquivos estáticos (opcional)
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
    }
}

# Configuração HTTPS (opcional)
# server {
#     listen 443 ssl http2;
#     listen [::]:443 ssl http2;
#     
#     server_name seu-dominio.com;
#     root /var/www/html;
#     index index.php;
#
#     ssl_certificate /path/to/cert.pem;
#     ssl_certificate_key /path/to/key.pem;
#     
#     # Resto da configuração igual ao bloco acima
# }
```

## 📖 Uso Básico

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use EDGVI10\Controllers\Router\RouterController;

// Criar instância do router
$router = new RouterController([
    "basePath" => "",           // Base path da aplicação (ex: "/api" ou "/v1")
    "useJson" => true,          // Retornar JSON por padrão
    "debug" => false,           // Modo debug (exibe stack trace)
    "logErrors" => true,        // Salvar logs de erros
    "logPath" => __DIR__ . "/logs/"  // Diretório dos logs
]);

// Definir rotas
$router->get('/', function ($req, $res) {
    $res->withJson(["message" => "Welcome to the API"]);
});

$router->get('/hello/:name', function ($req, $res, $params) {
    $res->withJson([
        "message" => "Hello, " . $params['name'] . "!"
    ]);
});

// Executar o router
$router->run();
```

## 🛣️ Rotas

### Métodos HTTP Suportados

```php
// GET
$router->get('/users', function ($req, $res) {
    $res->withJson(["users" => []]);
});

// POST
$router->post('/users', function ($req, $res) {
    $data = $req->getBody();
    $res->withJson(["created" => true, "data" => $data], 201);
});

// PUT
$router->put('/users/:id', function ($req, $res, $params) {
    $id = $params['id'];
    $data = $req->getBody();
    $res->withJson(["updated" => true, "id" => $id]);
});

// PATCH
$router->patch('/users/:id', function ($req, $res, $params) {
    $res->withJson(["patched" => true]);
});

// DELETE
$router->delete('/users/:id', function ($req, $res, $params) {
    $res->withJson(["deleted" => true]);
});

// OPTIONS (útil para CORS)
$router->options('/users', function ($req, $res) {
    $res->setHeader("Allow", "GET, POST, PUT, DELETE, OPTIONS");
    $res->withJson(["methods" => ["GET", "POST", "PUT", "DELETE"]]);
});
```

### Parâmetros Dinâmicos

```php
// Parâmetro simples
$router->get('/users/:id', function ($req, $res, $params) {
    $userId = $params['id'];
    $res->withJson(["userId" => $userId]);
});

// Múltiplos parâmetros
$router->get('/users/:userId/posts/:postId', function ($req, $res, $params) {
    $res->withJson([
        "userId" => $params['userId'],
        "postId" => $params['postId']
    ]);
});

// Parâmetro com regex
$router->get('/users/:id([0-9]+)', function ($req, $res, $params) {
    // Aceita apenas números
    $res->withJson(["userId" => $params['id']]);
});

$router->get('/posts/:slug([a-z0-9-]+)', function ($req, $res, $params) {
    // Aceita apenas letras minúsculas, números e hífens
    $res->withJson(["slug" => $params['slug']]);
});
```

## 📁 Grupos de Rotas

Organize rotas relacionadas com prefixos e middlewares compartilhados:

```php
use EDGVI10\Controllers\Router\Middlewares;

// Grupo de rotas da API v1
$router->group('/api/v1', function($router) {
    
    $router->get('/users', function ($req, $res) {
        $res->withJson(["users" => []]);
    });
    
    $router->get('/posts', function ($req, $res) {
        $res->withJson(["posts" => []]);
    });
    
}, [Middlewares::cors(), Middlewares::jsonOnly()]);

// Grupo de rotas protegidas
$router->group('/admin', function($router) {
    
    $router->get('/dashboard', function ($req, $res) {
        $res->withJson(["message" => "Admin dashboard"]);
    });
    
    $router->get('/users', function ($req, $res) {
        $res->withJson(["users" => ["admin1", "admin2"]]);
    });
    
}, [Middlewares::auth()]);

// Grupos aninhados
$router->group('/api', function($router) {
    
    $router->group('/v1', function($router) {
        $router->get('/test', function ($req, $res) {
            $res->withJson(["version" => "v1"]);
        });
    });
    
    $router->group('/v2', function($router) {
        $router->get('/test', function ($req, $res) {
            $res->withJson(["version" => "v2"]);
        });
    });
    
});
```

## 🔒 Middlewares

### Middlewares Globais

Aplicados a todas as rotas:

```php
use EDGVI10\Controllers\Router\Middlewares;

// CORS para todas as rotas
$router->addMiddleware(Middlewares::cors());

// Log de todas as requisições
$router->addMiddleware(function($req, $res, $params) {
    error_log("Request: " . $req->getMethod() . " " . $req->getRoute());
    return true; // continua para próxima rota
});
```

### Middlewares por Rota

```php
// Autenticação em rota específica
$router->get('/protected', function ($req, $res) {
    $res->withJson(["message" => "Protected content"]);
}, [Middlewares::auth()]);

// Múltiplos middlewares
$router->post('/admin/users', function ($req, $res) {
    $res->withJson(["created" => true]);
}, [
    Middlewares::auth(),
    Middlewares::adminOnly(),
    Middlewares::rateLimit(100)
]);
```

### Middlewares Prontos

```php
// CORS
Middlewares::cors([
    'origin' => '*',
    'methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    'headers' => 'Content-Type, Authorization'
]);

// Autenticação Bearer Token
Middlewares::auth(function($token, $request) {
    // Validar token no banco de dados
    if ($token === "seu-token-valido") {
        return ["id" => 1, "name" => "User"];
    }
    return false;
});

// API Key
Middlewares::apiKey('X-API-Key', function($key, $request) {
    return $key === "sua-api-key";
});

// Rate Limiting
Middlewares::rateLimit(100, 3600); // 100 requisições por hora

// Apenas JSON
Middlewares::jsonOnly();

// Validação de campos
Middlewares::validateFields(['name', 'email', 'password']);

// Admin apenas
Middlewares::adminOnly();
```

### Middleware Customizado

```php
$router->addMiddleware(function($req, $res, $params) {
    // Verificar IP
    $allowedIPs = ['127.0.0.1', '192.168.1.100'];
    $clientIP = $_SERVER['REMOTE_ADDR'];
    
    if (!in_array($clientIP, $allowedIPs)) {
        $res->withError("Access denied", 403);
        return false; // Para a execução
    }
    
    return true; // Continua para a próxima rota
});
```

## 📨 Request

Objeto com informações da requisição:

```php
$router->post('/example', function ($req, $res) {
    // Método HTTP
    $method = $req->getMethod(); // GET, POST, etc.
    
    // Rota atual
    $route = $req->getRoute(); // /example
    
    // Host completo
    $host = $req->getHost(); // https://example.com
    
    // Body da requisição (JSON)
    $body = $req->getBody(); // Array com dados JSON
    
    // Query parameters (?name=value)
    $query = $req->getQuery(); // Array
    $name = $req->getQuery('name'); // Valor específico
    
    // Headers
    $headers = $req->getHeaders(); // Array de headers
    $contentType = $req->getHeader('Content-Type');
    
    // User Agent
    $userAgent = $req->getUserAgent();
    
    // Token de autenticação (Bearer)
    $token = $req->getAuth();
    
    // Autenticação customizada
    $customAuth = $req->getAuthencation('X-Custom-Token');
    
    $res->withJson(["received" => $body]);
});
```

## 📤 Response

Objeto para enviar respostas:

```php
// JSON Response
$res->withJson(["success" => true], 200);

// Error Response
$res->withError("Not found", 404);
$res->withError("Unauthorized", 401);
$res->withError("Bad request", 400);

// HTML Response
$res->withHtml("<h1>Hello World</h1>", 200);

// Download de arquivo
$res->withDownload("/path/to/file.pdf", "documento.pdf");

// Status customizado
$res->withStatus(204); // No Content

// Adicionar headers
$res->setHeader("X-Custom-Header", "Value");
$res->setHeader("Cache-Control", "no-cache");

// Múltiplos headers
$res->setHeader("X-Custom", "Value")
    ->setHeader("X-Another", "Value2")
    ->withJson(["data" => "value"]);
```

## 💡 Exemplos Práticos

### API RESTful Completa

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use EDGVI10\Controllers\Router\RouterController;
use EDGVI10\Controllers\Router\Middlewares;

$router = new RouterController([
    "basePath" => "/api/v1",
    "useJson" => true,
    "debug" => true
]);

// Middleware global de CORS
$router->addMiddleware(Middlewares::cors());

// Rotas públicas
$router->post('/auth/login', function ($req, $res) {
    $body = $req->getBody();
    
    // Validar credenciais...
    if ($body['email'] === 'user@example.com' && $body['password'] === 'secret') {
        $res->withJson([
            "token" => "generated-token-here",
            "user" => ["id" => 1, "name" => "User"]
        ]);
    } else {
        $res->withError("Invalid credentials", 401);
    }
});

// Grupo de rotas protegidas
$router->group('/users', function($router) {
    
    // Listar usuários
    $router->get('', function ($req, $res) {
        $res->withJson(["users" => [
            ["id" => 1, "name" => "John Doe"],
            ["id" => 2, "name" => "Jane Doe"]
        ]]);
    });
    
    // Buscar usuário específico
    $router->get('/:id([0-9]+)', function ($req, $res, $params) {
        $id = $params['id'];
        // Buscar no banco...
        $res->withJson(["id" => $id, "name" => "John Doe"]);
    });
    
    // Criar usuário
    $router->post('', function ($req, $res) {
        $data = $req->getBody();
        // Salvar no banco...
        $res->withJson(["created" => true, "id" => 3], 201);
    });
    
    // Atualizar usuário
    $router->put('/:id', function ($req, $res, $params) {
        $id = $params['id'];
        $data = $req->getBody();
        // Atualizar no banco...
        $res->withJson(["updated" => true]);
    });
    
    // Deletar usuário
    $router->delete('/:id', function ($req, $res, $params) {
        $id = $params['id'];
        // Deletar do banco...
        $res->withJson(["deleted" => true]);
    });
    
}, [
    Middlewares::auth(function($token) {
        return $token === "generated-token-here";
    })
]);

$router->run();
```

### Com Autenticação e Rate Limit

```php
$router->group('/api', function($router) {
    
    $router->post('/data', function ($req, $res) {
        $res->withJson(["data" => "protected data"]);
    });
    
}, [
    Middlewares::auth(),
    Middlewares::rateLimit(100, 3600), // 100 req/hora
    Middlewares::jsonOnly()
]);
```

### Servindo HTML e Downloads

```php
$router->get('/', function ($req, $res) {
    $html = file_get_contents(__DIR__ . '/views/home.html');
    $res->withHtml($html);
});

$router->get('/download/report', function ($req, $res) {
    $res->withDownload(__DIR__ . '/files/report.pdf', 'relatorio-2025.pdf');
});
```

## 📝 Licença

Este projeto está licenciado sob a [Licença MIT](LICENSE).

## 👤 Autor

**Eduardo Vieira**

- GitHub: [@edgvi10](https://github.com/edgvi10)
- Email: <edgvi10@gmail.com>

---

⭐ Se este projeto foi útil para você, considere dar uma estrela no GitHub!
