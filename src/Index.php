<?php

namespace Ngt;

class Index
{
    public function __construct(
        $dimensions,
        $edgeSizeForCreation = 10,
        $edgeSizeForSearch = 40,
        $distanceType = DistanceType::L2,
        $objectType = ObjectType::Float,
        $path = null // private
    ) {
        $this->ffi = FFI::instance();

        $this->error = $this->ffi->ngt_create_error_object();
        $this->property = $this->call($this->ffi->ngt_create_property);

        if ($path && is_null($dimensions)) {
            $this->index = $this->call($this->ffi->ngt_open_index, $path);
            $this->call($this->ffi->ngt_get_property, $this->index, $this->property);
        } else {
            $this->call($this->ffi->ngt_set_property_dimension, $this->property, $dimensions);
            $this->call($this->ffi->ngt_set_property_edge_size_for_creation, $this->property, $edgeSizeForCreation);
            $this->call($this->ffi->ngt_set_property_edge_size_for_search, $this->property, $edgeSizeForSearch);

            switch ($objectType) {
                case ObjectType::Float:
                    $this->call($this->ffi->ngt_set_property_object_type_float, $this->property);
                    break;
                case ObjectType::Float16:
                    $this->call($this->ffi->ngt_set_property_object_type_float16, $this->property);
                    break;
                case ObjectType::Integer:
                    $this->call($this->ffi->ngt_set_property_object_type_integer, $this->property);
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown object type');
            }

            switch ($distanceType) {
                case DistanceType::L1:
                    $this->call($this->ffi->ngt_set_property_distance_type_l1, $this->property);
                    break;
                case DistanceType::L2:
                    $this->call($this->ffi->ngt_set_property_distance_type_l2, $this->property);
                    break;
                case DistanceType::Angle:
                    $this->call($this->ffi->ngt_set_property_distance_type_angle, $this->property);
                    break;
                case DistanceType::Hamming:
                    $this->call($this->ffi->ngt_set_property_distance_type_hamming, $this->property);
                    break;
                case DistanceType::Jaccard:
                    $this->call($this->ffi->ngt_set_property_distance_type_jaccard, $this->property);
                    break;
                case DistanceType::Cosine:
                    $this->call($this->ffi->ngt_set_property_distance_type_cosine, $this->property);
                    break;
                case DistanceType::NormalizedAngle:
                    $this->call($this->ffi->ngt_set_property_distance_type_normalized_angle, $this->property);
                    break;
                case DistanceType::NormalizedCosine:
                    $this->call($this->ffi->ngt_set_property_distance_type_normalized_cosine, $this->property);
                    break;
                default:
                    throw new \InvalidArgumentException('Unknown distance type');
            }

            $this->index = $this->call($this->ffi->ngt_create_graph_and_tree_in_memory, $this->property);
        }

        $this->dimensions = $this->call($this->ffi->ngt_get_property_dimension, $this->property);

        $objectType = $this->call($this->ffi->ngt_get_property_object_type, $this->property);
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

    public function __destruct()
    {
        $this->ffi->ngt_destroy_error_object($this->error);
        $this->ffi->ngt_close_index($this->index);
        $this->ffi->ngt_destroy_property($this->property);
    }

    public function insert($object)
    {
        return $this->call($this->ffi->ngt_insert_index, $this->index, $this->cObject($object), count($object));
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
            if (count($object) != $this->dimensions) {
                throw new \InvalidArgumentException('Bad dimensions');
            }

            foreach ($object as $v) {
                $obj[$i] = $v;
                $i++;
            }
        }

        $ids = $this->ffi->new("uint32_t[$count]");
        $this->call($this->ffi->ngt_batch_insert_index, $this->index, $obj, $count, $ids);

        $this->buildIndex(numThreads: $numThreads);

        $res = [];
        for ($i = 0; $i < $count; $i++) {
            $res[] = $ids[$i];
        }
        return $res;
    }

    public function object($id)
    {
        $objectSpace = $this->call($this->ffi->ngt_get_object_space, $this->index);
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
            return $this->call($this->ffi->ngt_remove_index, $this->index, $id);
        } catch (Exception $e) {
            return false;
        }
    }

    public function buildIndex($numThreads = 8)
    {
        return $this->call($this->ffi->ngt_create_index, $this->index, $numThreads);
    }

    public function search($query, $size = 20, $epsilon = 0.1, $radius = null)
    {
        $radius ??= -1.0;
        try {
            $results = $this->call($this->ffi->ngt_create_empty_results);
            $this->call($this->ffi->ngt_search_index, $this->index, $this->cObject($query), count($query), $size, $epsilon, $radius, $results);
            $resultSize = $this->call($this->ffi->ngt_get_result_size, $results);
            $ret = [];
            for ($i = 0; $i < $resultSize; $i++) {
                $res = $this->call($this->ffi->ngt_get_result, $results, $i);
                $ret[] = ['id' => $res->id, 'distance' => $res->distance];
            }
            return $ret;
        } finally {
            $this->ffi->ngt_destroy_results($results);
        }
    }

    public function save($path)
    {
        return $this->call($this->ffi->ngt_save_index, $this->index, $path);
    }

    public function close()
    {
        $this->ffi->ngt_close_index($this->index);
    }

    public static function load($path)
    {
        return new Index(null, path: $path);
    }

    private function cObject($object)
    {
        $count = count($object);
        if ($count != $this->dimensions) {
            throw new \InvalidArgumentException('Bad dimensions');
        }
        $cObject = $this->ffi->new("double[$count]");
        for ($i = 0; $i < $count; $i++) {
            $cObject[$i] = $object[$i];
        }
        return $cObject;
    }

    private function call($func, ...$args)
    {
        $args[] = $this->error;
        $res = $func(...$args);
        $message = $this->ffi->ngt_get_error_string($this->error);
        if ($message) {
            $this->ffi->ngt_clear_error_string($this->error);
            throw new Exception($message);
        }
        return $res;
    }
}
