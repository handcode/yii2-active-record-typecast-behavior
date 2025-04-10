<?php

namespace handcode\activeRecordTypecastBehavior;


use yii\base\Behavior;
use yii\base\Model;
use yii\db\TableSchema;
use yii\helpers\ArrayHelper;
use yii\validators\FilterValidator;

/**
 * This behavior can be used to typecast attribute values according to the db->schema types
 *
 * Why? Mainly to automate the filters required to solve the "int-cols are always dirty if submitted via http" problem
 * see: https://github.com/yiisoft/yii2/issues/4284
 *      https://www.yiiframework.com/doc/guide/2.0/en/input-validation#data-filtering
 *
 * The types for which attribute values should be casted according to the phpTypes of the tableSchema from the owner
 * are defined in `$this->typeFilter` property.
 *
 * The typeFilter struct is simply an array with entries like:
 * phpType => callbackName
 *
 * As in almost all scenarios there is no need to cast strings, the default typeFilter defines callbacks for "numeric"
 * types like integer & float
 * @see self::$typeFilter
 *
 * the filtering can be triggered on Model::EVENT_BEFORE_VALIDATE or Model::EVENT_AFTER_VALIDATE
 * @see self::$activeRecordAttributeTypeCastBeforeValidate
 * @see self::$activeRecordAttributeTypeCastAfterValidate
 *
 */
class ActiveRecordAttributeTypeCastBehavior extends Behavior
{

    /**
     * should we use the static caches to ensure we collect meta infos per owner::class only once per request?
     * Default = true
     *
     * @var bool
     */
    public $useStaticCache = true;

    /**
     * should typeCast Filtering be done on Model::EVENT_BEFORE_VALIDATE?
     * default == false
     *
     * @var bool
     */
    public $activeRecordAttributeTypeCastBeforeValidate = false;

    /**
     * should typeCast Filtering be done on Model::EVENT_AFTER_VALIDATE?
     * default == true
     *
     * @var bool
     */
    public $activeRecordAttributeTypeCastAfterValidate = true;

    /**
     * which callback (the values) should be triggered as FilterValidator::filter for which
     * phpTypes (from TableSchema::Columns)
     *
     * @var string[]
     */
    public $typeFilter = [
        'integer' => 'intval',
        'float'   => 'floatval',
    ];

    /**
     * internal cache var for schemas indexed by owner classes
     *
     * @var array
     */
    private static $_schemasByOwner = [];

    /**
     * internal cache var for types from schema indexed by owner classes
     *
     * @var array
     */
    private static $_typesByOwner = [];

    /**
     * internal cache var for typeFilter => [attributes] indexed by owner classes
     *
     * @var array
     */
    private static $_typeFilterAttributesByOwner = [];

    /**
     * {@inheritdoc}
     */
    public function events()
    {
        $events = [];

        if ($this->activeRecordAttributeTypeCastAfterValidate) {
            $events[Model::EVENT_AFTER_VALIDATE] = 'activeRecordAttributeTypeCastSchemaFilter';
        }
        if ($this->activeRecordAttributeTypeCastBeforeValidate) {
            $events[Model::EVENT_BEFORE_VALIDATE] = 'activeRecordAttributeTypeCastSchemaFilter';
        }

        return $events;
    }

    /**
     * Handles owner 'afterValidate' or 'beforeValidate' event,
     * ensuring attribute typecasting defined in self::getAttributesTypeFilter
     *
     * @param \yii\base\Event $event event instance.
     */
    public function activeRecordAttributeTypeCastSchemaFilter($event)
    {

        if (!empty($this->getTypeFilterAttributes())) {
            foreach ($this->getTypeFilterAttributes() as $filter => $attributes) {
                /** @var FilterValidator $validator */
                $validator = \Yii::createObject(
                    [
                        'class' => FilterValidator::class,
                        'filter' => $this->typeFilter[$filter],
                        // does only work if we call $validator->validateAttributes() NOT for $validator->validateAttribute() !!
                        // see: \yii\validators\FilterValidator::validateAttribute
                        'skipOnEmpty' => true,
                        // to be able to call $validator->validateAttributes, we MUST define the attribute names that should be filtered here.
                        // see: \yii\validators\Validator::validateAttributes
                        //      \yii\validators\Validator::getValidationAttributes
                        'attributes' => $attributes,
                    ]
                );
                // attributes for this filter MUST be defined in $validator->attributes, otherwise they will be ignored.
                // see above.
                \Yii::debug($this->owner->getDirtyAttributes());
                $validator->validateAttributes($this->owner);
                \Yii::debug($this->owner->getDirtyAttributes());
            }
        }
    }

    /**
     * get the list of attribute names that should be filtered for defined types (the array keys) in $this->typeFilter
     * @use self::_typeFilterAttributesByOwner
     *
     * @return array|mixed
     */
    protected function getTypeFilterAttributes()
    {
        if (!array_key_exists($this->owner::class, self::$_typeFilterAttributesByOwner)) {
            $filterAttributesByType = [];
            foreach ($this->getAttributeTypesFromOwnerSchema() as $name => $type) {
                if (array_key_exists($type, $this->typeFilter)) {
                    if (! array_key_exists($type, $filterAttributesByType)) {
                        $filterAttributesByType[$type] = [];
                    }
                    $filterAttributesByType[$type][] = $name;
                }
            }
            self::$_typeFilterAttributesByOwner[$this->owner::class] = $filterAttributesByType;
        }
        return self::$_typeFilterAttributesByOwner[$this->owner::class] ?: [];
    }

    /**
     * get array with column->name => column->phpType from static cache or owner TableSchema
     * @use self::_typesByOwner
     *
     * @return array
     */
    protected function getAttributeTypesFromOwnerSchema()
    {
        if (!array_key_exists($this->owner::class, self::$_typesByOwner)) {
            self::$_typesByOwner[$this->owner::class] = ArrayHelper::map($this->getOwnerSchema()->columns, 'name', 'phpType');
        }
        return self::$_typesByOwner[$this->owner::class] ?: [];

    }

    /**
     * get owner schema from $this->owner::getTableSchema()
     * @use self::_schemasByOwner
     *
     * @return TableSchema
     */
    protected function getOwnerSchema()
    {
        if (!array_key_exists($this->owner::class, self::$_schemasByOwner)) {
            self::$_schemasByOwner[$this->owner::class] = $this->owner::getTableSchema();
        }
        return self::$_schemasByOwner[$this->owner::class];
    }

}