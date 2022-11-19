<?php

namespace Ngt;

// TODO use enum when PHP 8.0 reaches EOL
class DistanceType
{
    public const L1 = 0;
    public const L2 = 1;
    public const Hamming = 2;
    public const Angle = 3;
    public const Cosine = 4;
    public const NormalizedAngle = 5;
    public const NormalizedCosine = 6;
    public const Jaccard = 7;
}
