<?php

namespace Ngt;

class Index
{
    private $ffi;
    private $error;
    private $property;
    private $index;
    private $dimensions;
    private $objectType;

    public function __construct(
        $dimensions,
        $edgeSizeForCreation = 10,
        $edgeSizeForSearch = 40,
        $distanceType = DistanceType::L2,
        $objectType = ObjectType::Float,
        $path = null // private
    ) {
        $this->ffi = FFI::instance();

        $this->error = new Pointer($this->ffi->ngt_create_error_object(), $this->ffi->ngt_destroy_error_object);
        $this->property = new Pointer($this->call($this->ffi->ngt_create_property), $this->ffi->ngt_destroy_property);
        $property = $this->property->ptr;

        if ($path && is_null($dimensions)) {
            $this->index = new Pointer($this->call($this->ffi->ngt_open_index, $path), $this->ffi->ngt_close_index);
            $this->call($this->ffi->ngt_get_property, $this->index->ptr, $property);
        } else {
            $this->call($this->ffi->ngt_set_property_dimension, $property, $dimensions);
            $this->call($this->ffi->ngt_set_property_edge_size_for_creation, $property, $edgeSizeForCreation);
            $this->call($this->ffi->ngt_set_property_edge_size_for_search, $property, $edgeSizeForSearch);

            switch ($objectType) {
                case ObjectType::Float:
                    $this->call($this->ffi->ngt_set_property_object_type_float, $property);
                    break;
                case ObjectType::Float16:
                    $this->call($this->ffi->ngt_set_property_object_type_float16, $property);
                    break;
                case ObjectType::Integer:
                    $this->call($this->ffi->ngt_set_property_object_type_integer, $property);
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown object type');
            }

            switch ($distanceType) {
                case DistanceType::L1:
                    $this->call($this->ffi->ngt_set_property_distance_type_l1, $property);
                    break;
                case DistanceType::L2:
                    $this->call($this->ffi->ngt_set_property_distance_type_l2, $property);
                    break;
                case DistanceType::Angle:
                    $this->call($this->ffi->ngt_set_property_distance_type_angle, $property);
                    break;
                case DistanceType::Hamming:
                    $this->call($this->ffi->ngt_set_property_distance_type_hamming, $property);
                    break;
                case DistanceType::Jaccard:
                    $this->call($this->ffi->ngt_set_property_distance_type_jaccard, $property);
                    break;
                case DistanceType::Cosine:
                    $this->call($this->ffi->ngt_set_property_distance_type_cosine, $property);
                    break;
                case DistanceType::NormalizedAngle:
                    $this->call($this->ffi->ngt_set_property_distance_type_normalized_angle, $property);
                    break;
                case DistanceType::NormalizedCosine:
                    $this->call($this->ffi->ngt_set_property_distance_type_normalized_cosine, $property);
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown distance type');
            }

            $this->index = new Pointer($this->call($this->ffi->ngt_create_graph_and_tree_in_memory, $property), $this->ffi->ngt_close_index);
        }

        $this->dimensions = $this->call($this->ffi->ngt_get_property_dimension, $property);

        $objectType = $this->call($this->ffi->ngt_get_property_object_type, $property);
        if ($this->ffi->ngt_is_property_object_type_float($objectType)) {
            $this->objectType = ObjectType::Float;
        } elseif ($this->ffi->ngt_is_property_object_type_float16($objectType)) {
            $this->objectType = ObjectType::Float16;
        } elseif ($this->ffi->ngt_is_property_object_type_integer($objectType)) {
            $this->objectType = ObjectType::Integer;
        } else {
            throw new Exception('Unknown object type');
        }
    }

    public function insert($object)
    {
        return $this->call($this->ffi->ngt_insert_index, $this->index->ptr, $this->cObject($object), count($object));
    }

    // TODO add option to not build index
    public function batchInsert($objects, $numThreads = 8)
    {
        $count = count($objects);
        if ($count == 0) {
            return [];
        }

        $obj = $this->ffi->new('float[' . ($this->dimensions * $count) . ']');
        $i = 0;
        foreach ($objects as $object) {
            $this->checkDimensions(count($object));

            foreach ($object as $v) {
                $obj[$i] = $v;
                $i++;
            }
        }

        $ids = $this->ffi->new("uint32_t[$count]");
        $this->call($this->ffi->ngt_batch_insert_index, $this->index->ptr, $obj, $count, $ids);

        $this->buildIndex(numThreads: $numThreads);

        $res = [];
        for ($i = 0; $i < $count; $i++) {
            $res[] = $ids[$i];
        }
        return $res;
    }

    public function object($id)
    {
        $objectSpace = $this->call($this->ffi->ngt_get_object_space, $this->index->ptr);
        if ($this->objectType == ObjectType::Integer) {
            $res = $this->call($this->ffi->ngt_get_object_as_integer, $objectSpace, $id);
        } elseif ($this->objectType == ObjectType::Float) {
            $res = $this->call($this->ffi->ngt_get_object_as_float, $objectSpace, $id);
        } else {
            throw new Exception('Method not supported for this object type');
        }
        $ret = [];
        for ($i = 0; $i < $this->dimensions; $i++) {
            $ret[] = $res[$i];
        }
        return $ret;
    }

    public function remove($id)
    {
        try {
            return $this->call($this->ffi->ngt_remove_index, $this->index->ptr, $id);
        } catch (Exception $e) {
            return false;
        }
    }

    public function buildIndex($numThreads = 8)
    {
        return $this->call($this->ffi->ngt_create_index, $this->index->ptr, $numThreads);
    }

    public function search($query, $size = 20, $epsilon = 0.1, $radius = null)
    {
        $radius ??= -1.0;
        $results = new Pointer($this->call($this->ffi->ngt_create_empty_results), $this->ffi->ngt_destroy_results);
        $this->call($this->ffi->ngt_search_index, $this->index->ptr, $this->cObject($query), count($query), $size, $epsilon, $radius, $results->ptr);
        $resultSize = $this->call($this->ffi->ngt_get_result_size, $results->ptr);
        $ret = [];
        for ($i = 0; $i < $resultSize; $i++) {
            $res = $this->call($this->ffi->ngt_get_result, $results->ptr, $i);
            $ret[] = ['id' => $res->id, 'distance' => $res->distance];
        }
        return $ret;
    }

    public function save($path)
    {
        return $this->call($this->ffi->ngt_save_index, $this->index->ptr, $path);
    }

    public function close()
    {
        $this->ffi->ngt_close_index($this->index->ptr);
    }

    public static function load($path)
    {
        return new Index(null, path: $path);
    }

    private function cObject($object)
    {
        $count = count($object);
        $this->checkDimensions($count);
        $cObject = $this->ffi->new("double[$count]");
        for ($i = 0; $i < $count; $i++) {
            $cObject[$i] = $object[$i];
        }
        return $cObject;
    }

    private function checkDimensions($d)
    {
        if ($d != $this->dimensions) {
            throw new \InvalidArgumentException('Bad dimensions');
        }
    }

    private function call($func, ...$args)
    {
        $error = $this->error->ptr;
        $args[] = $error;
        $res = $func(...$args);
        $message = $this->ffi->ngt_get_error_string($error);
        if ($message) {
            $this->ffi->ngt_clear_error_string($error);
            throw new Exception($message);
        }
        return $res;
    }
}
