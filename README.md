# Laravel Bread

A library to easily handle BREAD (Browse, Read, Edit, Add, Delete) operations through a JSON Rest API. Define model definitions, performs validations and provides default controller routes.

This package makes use of the API Query Builder from https://github.com/jesperbjerke/laravel-api-query-builder

## Installation

```shell script
composer require bjerke/laravel-bread
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

Other configuration items are default field groups available to all models as well as some options related to TUS uploads.

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

// If you want to use TUS uploads add this route
Route::any('tus/{chunkId?}', [UserController::class, 'tus']); // Handle TUS uploads
```

Remember to only define the endpoints that you actually want to use. And set proper authentication and authorization rules.

If you want to provide field definitions and automatic validation etc, you need to use the `FieldDefinition` trait and define the fields on your model.

```php
class User
{
    use FieldDefinition;
    use QueryBuilderModelTrait;
    use BreadModelTrait;

    protected function define(DefinitionBuilder $definition): DefinitionBuilder
    {
        $definition->addFields([
            (new TextField('first_name'))->label(Lang::get('fields.first_name'))->required(true),

            (new TextField('last_name'))->label(Lang::get('fields.last_name'))->required(true),

            (new EmailField('email'))
                ->label(Lang::get('fields.email'))
                ->required(true)
                ->addValidation('unique:users,email' . (($this->exists) ? (',' . $this->id) : ''))
        ]);
        return $definition;
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

### Saving files/images

In order for the built-in functionality of storing files and/or images, this package requires the https://github.com/spatie/laravel-medialibrary to be installed and configured.

Add files/images:
```json
{
  "data" : {
    "files" : [{
      "base64" : "base64 encoded string representation of the image/file",
      "name" : "Filename",
      "add" : true
    }]
  }
}
```

Remove files/images:
```json
{
  "data" : {
    "files" : [{
      "id" : 1,
      "remove" : true
    }]
  }
}
```

### TUS uploads

There is build in functionality to support uploads using the [TUS protocol](https://tus.io/). To enable TUS uploads, you need to install the package https://github.com/ankitpokhrel/tus-php in addition to the media library package specified above.

You can then use a TUS client like https://github.com/tus/tus-js-client or https://uppy.io/.

When submitting the files, you send the unique upload key received from the TUS server (a UUID4 string) instead of base64. Note that it is expected that the file has already been uploaded through TUS  and exists on the server before this request is sent.
```json
{
  "data" : {
    "files" : {
      "tusKey" : "002f12d4-6949-4266-af06-675f653c0bdc",
      "name" : "Filename",
      "add" : true
    }
  }
}
```

#### Cleaning orphaned TUS files

Add `$schedule->command('bread:clean-tus --force')->daily()` to your scheduler to automatically clean up orphaned/aborted files.
