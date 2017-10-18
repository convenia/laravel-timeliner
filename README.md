<h1 align="center">
Laravel Timeliner (with DynamoDB)
</h1>


&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

<!-- bedges -->

## Basic Overview

Create a timeline Mirroring your models or add standalone events


## Install
```bash
composer require convenia/timeliner
```

###  publish and register
```bash
php artisan vendor:publish --tag="checklistable"
```



<br>

## Usage

###  configure your mirroring models

```bash
      use Timelinable;
	      
      public $mirrorableFormat = [
       'event-name' = [
         'fields' => [
           'field' => 'somefunction|function',
           'category' => 'category_name|static',
           'date' => 'created_at'
        ],
        'tags' => [
          'something|static',
          'model_field',
          'model.relation_field'
        ],
        'pinned' => 'model_field',
        'category' => 'model.relation_field'
```

#### Or add event manually


```php
	$timelineService->createFromData();
```

## License

Checklistable is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
