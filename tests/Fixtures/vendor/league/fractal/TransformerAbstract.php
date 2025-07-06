<?php

namespace League\Fractal;

abstract class TransformerAbstract
{
    protected $availableIncludes = [];
    protected $defaultIncludes = [];
    
    abstract public function transform($data);
    
    protected function item($data, $transformer)
    {
        return ['data' => $data, 'transformer' => $transformer];
    }
    
    protected function collection($data, $transformer)
    {
        return ['data' => $data, 'transformer' => $transformer];
    }
    
    protected function null()
    {
        return null;
    }
}