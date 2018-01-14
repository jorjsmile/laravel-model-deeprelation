<?php
/**
 * Created by PhpStorm.
 * User: george
 * Date: 8/7/15
 * Time: 1:56 PM
 * Adds additional ability to save/update related models
 *
 * @author George <jorjSmile@gmail.com>
 * Laravel 5 version
 */

namespace Jorjsmile\LaravelModelDeepRelation;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait TDeepRelation
 * @package App\Common\Models
 * @mixin EloquentModel
 */
trait TDeepRelation {

    /**
     * @var array|null - empty array - will populate all, only false|null will stop fill the relation.
     *  Also note, it u will populate this array, you will disable all relations auto-fill
     */
    public $deepFillable = [];

    /**
     * You can set list of relations that should be saved altogether with current model.
     * like this ['relation1' => [ 'relation1.deeperRelation' => [] ]] - note others will not be saved by the way
     * To save only desired relation  and not deeper one, just set ['relation1'=>false].
     * Also note, if relation doesn't have this trait, it will be simply saved.
     *
     * If you will set deepStrategy to false. It will simply  save model
     *
     * @param array|bool $saveStrategy
     * @return bool
     */
    public function deepSave($saveStrategy = [])
    {

        if($saveStrategy !== false){
            static::saving(function($object) use($saveStrategy){
                static::deepBeforeSave($object, $saveStrategy);
            });

            static::saved(function($object) use ($saveStrategy) {
                static::deepAfterSave($object, $saveStrategy);

            });
        }

        $result = $this->save();

        return $result;
    }


    /**
     * Filter relations before save
     *
     * @param EloquentModel $object
     * @param array $strategy
     * @return array
     */
    protected static function strategyRelations(EloquentModel $object, $strategy = []) : array
    {
        if($strategy === false) return []; //if a throttle strategy chosen

        $relations = $object->getRelations();

        //if no strategy provided
        if(is_array($strategy) && empty($strategy)) return $relations;

        return array_intersect_key($relations, $strategy);
    }

    /**
     * Prepare relations before save
     *
     * @param EloquentModel|TDeepRelation $object
     * @param array $strategy should it dive deeper
     *
     * @return bool
     */
    protected static function deepBeforeSave( EloquentModel $object, $strategy = [] )
    {
        $relations = self::strategyRelations($object, $strategy);

        if(empty($relations)) return true;

        foreach ($relations as $name => $data) {
            if(!method_exists($object, $name)) continue;

            $relation = $object -> $name();

            if(get_class($relation) !== BelongsTo::class) continue;
            $model = $object->getRelation($name);

            if(!($model instanceof EloquentModel)) continue;
            $objectStrategy = $strategy[$name]??[];

            $object->saveBelongsTo($model, $relation, $objectStrategy);
        }

        return true;
    }

    /**
     * Set relations after save model
     *
     * @param EloquentModel|TDeepRelation $object
     * @return bool|int
     */
    protected static function deepAfterSave(EloquentModel $object, $strategy = []){

        $relations = self::strategyRelations($object, $strategy);

        if(empty($relations)) return true;

        foreach ($relations as $name => $data) {
            if(!method_exists($object, $name)) continue;

            $relation = $object->$name();
            $objectStrategy = $strategy[$name]??[];

            switch (get_class($relation)) {
                case HasOne::class :
                    if(!$data instanceof EloquentModel) continue;
                    $object->saveHasOne($name, $data, $relation, $objectStrategy);
                    break;

                case HasMany::class :
                    $object->saveHasMany($name, $data, $relation, $objectStrategy);
                    break;

                case BelongsToMany::class:
                    $object->saveManyMany($name, $data, $relation, $objectStrategy);
                    break;

                default:
                    break;
            }
        }
        return true;
    }

    /**
     * Will save belongsTo and fill set appropriate foreign key
     *
     * @param TDeepRelation|EloquentModel $relationModel
     * @param BelongsTo $relation
     * @param array $strategy
     */
    public function saveBelongsTo( EloquentModel $relationModel, BelongsTo $relation, $strategy = [])
    {
        if(method_exists($relationModel, "deepSave"))
            $relationModel->deepSave($strategy);
        else
            $relationModel->save(); //apply to database, and possible deep savings

        $oK = $relation->getOwnerKey();

        $this->{$relation->getForeignKey()} = $relationModel->$oK; //initialize parents foreign key
    }

    /**
     * Will save HasOne and fill set appropriate foreign key
     *
     * @param $name
     * @param EloquentModel $data
     * @param Relation $relation
     * @param array $strategy
     */
    public function saveHasOne( $name, EloquentModel $data, Relation $relation, $strategy = [])
    {
        $relatedModel = $this->saveHasOneManyRelation($data, $relation, $strategy);
        $this->setRelation($name, $relatedModel);
    }

    /**
     * Save related model
     *
     * @param EloquentModel $relatedModel
     * @param Relation $relation
     * @param array $strategy
     *
     * @return EloquentModel
     */
    protected function saveHasOneManyRelation(EloquentModel $relatedModel, Relation $relation, $strategy = [])
    {
        /**
         * @var HasOne $relation
         */
        $parentPK = $relation->getParentKey();

        $relatedModel->{$relation->getForeignKeyName()} = $parentPK;

        if(method_exists($relatedModel, "deepSave"))
            $relatedModel->deepSave($strategy);
        else
            $relatedModel->save(); //apply to database, and possible deep savings

        return $relatedModel;
    }

    /**
     * Set has many relation
     *
     * @param string $name
     * @param array $data
     * @param Relation $relation
     * @param array $strategy
     */
    public function saveHasMany($name, $data, $relation, $strategy = [])
    {
        $result = [];
        foreach ($data as $k=>$d) {
            $relatedModel = $this->saveHasOneManyRelation($d, $relation, $strategy);
            $result[$k] = $relatedModel;
        }
        $this->setRelation($name, new Collection($result));
    }

    /**
     * Set many-to-many relation
     *
     * @param string $name
     * @param array $data
     * @param BelongsToMany $relation
     * @param array $strategy
     */
    public function saveManyMany($name, $data, $relation, $strategy = [])
    {
        $result = [];
        $insert = [];

        $pointerTable = $relation->getTable();
        $ownerForeign = explode(".", $relation->getQualifiedForeignPivotKeyName());
        $ownerForeign = end($ownerForeign);

        $thirdForeign = explode(".", $relation->getQualifiedRelatedPivotKeyName());
        $thirdForeign = end($thirdForeign);

        $data = $data instanceof Collection? $data->all() : $data;

        \DB::table($pointerTable)
            ->where([ $ownerForeign => $this->getKey() ])
            ->delete();//new Builder();

        $data = array_filter($data); //skip empty objects

        if(empty($data))
        {
            $this->setRelation($name, new Collection([]));
            return ;
        }

        foreach ($data as $nr => $d) {

            if(method_exists($d, "deepSave"))
                $d->deepSave($strategy);
            else
                $d->save(); //apply to database, and possible deep savings

            $result[] = $d;
            $insert[] = [ $ownerForeign => $this->getKey(), $thirdForeign => $d->getKey()  ];
        }

        \DB::table($pointerTable)
            ->insert($insert);//new Builder();

        $this->setRelation($name, new Collection($result));
    }




    /**
     * Populate model with relations also
     * @param array $data
     *
     * @return TDeepRelation|EloquentModel
     */
    public function deepFill($data)
    {
        $this->fill($data); //make default  fill
        $extraData = array_diff_key($data, array_flip($this->fillable));

        $fillableRelations = is_array($this->deepFillable) && !Arr::isAssoc($this->deepFillable)?
                                array_flip($this->deepFillable) :
                                $this->deepFillable;  // ['a', 'b'] => ['a'=>1, 'b'=>1]
        /**
         * @todo filter by deepGuardedRelations
         */
        foreach ($extraData as $name => $value) {
            if(!method_exists($this,$name) /*&& !method_exists($this, $var = Str::camel($var))**/) continue;

            //if dee
            if($fillableRelations === false || (!empty($fillableRelations) && !isset($fillableRelations[$name]))) continue;

            $this->deepSetRelation($name, $value);
        }

        return $this;
    }

    /**
     * @param string $name Relation name
     * @param array  $data Relation attributes
     */
    public function deepSetRelation($name, $data){
        if(empty($data)) return;

        /**
         * @var Relation $relation
         */
        $relation = $this->$name();
        $default = $this->relationLoaded($name)? $this->$name : null;

        switch (get_class($relation)) {

            case BelongsTo::class :
            case HasOne::class :

                $this->setRelation( $name, $this->resolveRelated($relation, $data, $default) );
                break;

            case HasMany::class :
            case BelongsToMany::class :

                $result = [];
                $related = $relation->getModel();

                $ids = [];
                foreach ($data as $k => $d) {
                    if (is_numeric($d))  //is id
                        $ids[] = $d;
                    else
                        $result[] = $this->resolveRelated($relation, $d);
                }

                if(!empty($ids)){
                    $related = $this->loadRelatedByPk($related, $ids); //primary key
                    if ($related instanceof BaseCollection && $related->count())
                        $result = array_merge($result, $related->all());
                }

                $this->setRelation($name, new Collection($result));
                break;
        }
    }

    /**
     * @param EloquentModel $model
     * @param $data
     * @return int
     */
    private function explorePk( EloquentModel $model, $data) : int
    {
        if(is_int($data)) return $data;

        if($data instanceof EloquentModel)
            return $data->getKey();

        if(!is_array($data)) return -1;

        return $data[$model->getKeyName()]??-1;
    }


    /**
     * Load related record by primary key
     *
     * @param EloquentModel $model
     * @param mixed  $pk
     *
     * @return mixed
     */
    private function loadRelatedByPk($model, $pk)
    {

        $query = $model->newQuery();
        if(is_int($pk))
            $ar = $query->find($pk);
        else
            $ar = $query->whereIn($model->getKeyName(), $pk)->get();

//        if (!$ar && getenv("APP_DEBUG"))
//            throw new \ErrorException("Record not found");

        return $ar;
    }


    /**
     * Get relation model
     *
     * @param Relation $relation
     * @param array $data
     * @param EloquentModel $default
     * @return EloquentModel
     */
    private function resolveRelated($relation, $data, $default=null) : EloquentModel
    {
        if ($data instanceof EloquentModel)
            return $data;

        /**
         * @var null|TDeepRelation|EloquentModel $object
         */
        $object = $default;

        if(!$object){

            $pk = $this->explorePk($relation->getModel(), $data);

            if($pk !== -1)
                $object = $this->loadRelatedByPk($relation->getModel(), $pk);
            else{
                $class = get_class($relation->getModel());
                $object = new $class;
            }
        }

        if(!is_array($data) || empty($data)) return $object;

        if(method_exists($object, "deepFill")) //has this trait
            $object->deepFill($data); //go deeper
        else{
            $object->fill(array_intersect_key( $data, array_flip($object->getFillable()) ));
        }

        return $object;
    }

}