<?php

namespace Ngt;

enum DistanceType
{
    case L1;
    case L2;
    case Hamming;
    case Angle;
    case Cosine;
    case NormalizedAngle;
    case NormalizedCosine;
    case Jaccard;
}
