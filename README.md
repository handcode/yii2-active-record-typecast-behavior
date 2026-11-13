# yii2-active-record-typecast-behavior

This behavior can be used to typecast yii2 ActiveRecord attribute values according to the db->schema types

Why? 

To automate the filters required to solve the "int-cols are always dirty if submitted via http" problem. 

see:
- https://github.com/yiisoft/yii2/issues/4284
- https://www.yiiframework.com/doc/guide/2.0/en/input-validation#data-filtering

## config

the attribute typecast is done by calling `\yii\validators\FilterValidator` with defined filter callback according to db schema phpType
all attributes with types not defined in the typeFilter array of the behavior will be ignored.

The default typeFilter definition is:
```php
public $typeFilter = [
    'integer' => 'intval',
    'float'   => 'floatval'
];
```
