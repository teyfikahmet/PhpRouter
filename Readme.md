# PhpRouter
var bir hayalimiz :heart:

## Kullanımı
### Başlatma
```php
$router = new Router([
    'controller_namespace' => 'App\\Controllers\\',
    'middleware_namespace' => 'App\\Middlewares\\',
]);
```
### Methodlar
```php
$router->get('home', '/', [Home::class, 'index']);
// Alternatif
$router->get('home', '/', 'Home@index');
// Alternatif
$router->get('home', '/', function(Request $request, Response $response){
    return '<h1>Hello World</h1>';
});
$router->get('profile', '/profile/:string', function(Request $request, Response $response, $username){
    return 'Hi, ' . $username;
});
```
