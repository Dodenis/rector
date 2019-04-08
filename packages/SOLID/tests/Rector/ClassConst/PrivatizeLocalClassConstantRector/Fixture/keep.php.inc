<?php

namespace Rector\SOLID\Tests\Rector\ClassConst\PrivatizeLocalClassConstantRector\Fixture;

class ClassWithConstantUsedSomewhereElse
{
    const LOCAL_ONLY = true;
}

class ForeignConstantAddictUser
{
    public function run()
    {
        return ClassWithConstantUsedSomewhereElse::LOCAL_ONLY;
    }
}
