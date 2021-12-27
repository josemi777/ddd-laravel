# DDD Laravel - version (1.1.0)
 CRUD wizard for creating DDD classes and dependency injections.

This library is designed to add CRUD commands of elements with DDD structure in your [Laravel](https://laravel.com/) project.
These commands are used by "php artisan" (you can get a list of them using `php artisan`) and are as follows:

* make-ddd
  * [make-ddd:dump-dependencies](#dump-dependencies)
  * [make-ddd:end-point](#create-an-endpoint)
  * [make-ddd:interface](#create-an-interface)
  * [make-ddd:thing](#create-thing)


## Installation

You can install it using [composer](https://getcomposer.org/) with next command, or simply adding `jmsr/ddd-laravel` to your composer.json file.

```bash
composer require jmsr/ddd-laravel
```

## Commands

### Dump dependencies

```bash
php artisan make-ddd:dump-dependencies
```
This command automatically creates the necessary relationships, in Laravel, between constructors and dependencies that are reflected in the `src/config/injections.yml` file.

Example of relations for the `src/config/injections.yml` file:

```yaml
TestOneRepository:
    class: App\src\Infrastructure\Repository\Test\TestOneRepository

TestTwoRepository:
    class: App\src\Infrastructure\Repository\TestTwoRepository


TestService:
    class: App\src\Infrastructure\Service\Test\TestService
    neededClass: 
        - '@TestOneRepository'
        - '@TestTwoRepository'
        - App\src\Infrastructure\Repository\TestThreeRepository
```

### Create an Endpoint

```bash
php artisan bashmake-ddd:end-point
```
With this command you can automatically create the endpoints of your application, based on the usecases created. You just need to follow the simple guide that will ask you questions after executing the command.

You can create an endpoint passing UseCase route by command line parameter:
```bash
php artisan bashmake-ddd:end-point --usecase={complete route of the UseCase}
```

The default values to create the endpoint are the following (you can change them during the executting of assistance):

- $route_name (null): You can define a direct name for the route, just as Laravel routes allow.
- $method (post): Allowed method to call the endpoint, you can set multiple methods, separated by commas. (example: post, get, put).
- $url (null): Path to call the endpoint, if the value is null it will be generated automatically based on the name of the UseCase.
- $function (execute): Endpoint function that will be used by default when calling the endpoint (this default value is "execute").
- $file (web): For which Laravel routes file the endpoint will be created, the two options are "web" or "api".

### Create an Interface

```bash
php artisan make-ddd:interface
```

Just create an interface in the directory space `src/Application/Domain`

### Create Thing

```bash
php artisan make-ddd:thing
```

With this command we can create several types of elements given in DDD architecture, which are `usecase`,` service`, `repository` and` model`.
In the case of creating a service or a repository, an associated interface will be created automatically in the `src / Application` directory.