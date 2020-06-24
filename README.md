# Laravel Bread

A library to easily handle BREAD (Browse, Read, Edit, Add, Delete) operations through a JSON Rest API. Define model definitions, performs validations and proivdes default controller routes.

This package makes use of the API Query Builder from https://github.com/jesperbjerke/laravel-api-query-builder

## Installation

Add the following to your composer.json. Make sure your git user is allowed to access this repository.

```shell script
composer require bjerke/bread
```

### Configuration

There is not much to configure, but one thing that can be configured is what namespace to use for your models (used when trying to lookup the model in the controller).
It is preconfigured to use `\App\Models\`. If you don't need to change any of the default configuration options there's no need to publish this configuration.

If you do want to change the default namespace however, you need to publish the configuration file from this library so you can change it in your own application.
To do this run the following artisan command:
```sh
php artisan vendor:publish --provider="Bjerke\Bread\BreadServiceProvider"
```

You will now have a `bread.php` config file in `/config` where you can change the configuration values.

## Usage

To start using this package, you need to create your own controller class, name it in singular matching the model it's affecting.
For example `UserController` will lookup the `User` model automatically. If you don't follow this pattern, you can define the `$modelName` property on the controller.

Extend your controller on `BreadController` from this package. Then in your model, use the `QueryBuilderModelTrait` and `BreadModelTrait`.

Lastly, define the API endpoints in your router.

```php
Route::get('definition', [UserController::class, 'definition']); // Fetch model field definitions

Route::get('', [UserController::class, 'index']); // Query multiple users, uses ApiQueryBuilder
Route::post('', [UserController::class, 'create']); // Create new user
Route::get('{id}', [UserController::class, 'view']); // Fetch single user
Route::patch('{id}', [UserController::class, 'update']); // Update user
Route::delete('{id}', [UserController::class, 'delete']); // Delete user
Route::delete('{id}/detach/{relatedModel}', [UserController::class, 'detach']); // Attach an existing model to be related to user
Route::put('{id}/attach/{relatedModel}', [UserController::class, 'attach']); // Detach an existing related model from user
```

Remember to only define the endpoints that you actually want to use. And set proper authentication and authorization rules.

If you want to provide field definitions and automatic validation etc, you need to use the `FieldDefinition` trait and define the fields on your model.

```php
class User
{
    use FieldDefinition,
        QueryBuilderModelTrait,
        BreadModelTrait;

    protected function define()
    {
        $this->addFieldText('first_name', Lang::get('fields.first_name'), self::$FIELD_REQUIRED);
        $this->addFieldText('last_name', Lang::get('fields.last_name'), self::$FIELD_REQUIRED);
        
        $this->addFieldEmail('email', Lang::get('fields.email'), self::$FIELD_REQUIRED, [
            'validation' => 'email|unique:users,email' . (($this->exists) ? (',' . $this->id) : '')
        ]);
    }
}
```

The above definition together with the routes will allow you to post the following to `/users` to create a new user:
```json
{
  "data" : {
    "first_name" : "John",
    "last_name" : "Doe",
    "email" : "john.doe@test.com"
  }
}
```
Note the data you send is expected to be within the `data` property. 

More about available field types and how they work is described in each fields docblock in `src/Traits/FieldDefinition.php`.

### Modifying queries in BREAD controller endpoints

You can hook into the query builder and append your own rules on endpoints that fetch data from the database.

Example:
```php
class ProductController extends BreadController
{
    public function index(Request $request, $applyQuery = null)
    {
        return parent::index($request, static function (Builder $query) {
            $query->where('distributor_id', \Auth::user()->distributor_id);
        });
    }
}
```


### Run logic before/after Updating/Deleting/Attaching/Detaching

The breadcontroller provides hooks to allow you to run custom logic before the actual saving proceeds. If you want to stop the execution, throw an appropriate exception.
Use the hooks by implementing the endpoint method in your controller, and passing a closure to the parent method. This works on all endpoint methods.

Example:
```php
class ProductController extends BreadController
{
    public function update(Request $request, $id, $with = [], $applyQuery = null, $beforeSave = null)
    {
        /* @var Product $product */
        $product = parent::update($request, $id, $with, $applyQuery, static function (Product $product) {
            // Set a custom property on the model before saving
            $product->last_updated_by = \Auth::id();
        });

        return $product;
    }
}
```

### Saving images

Requires media library
