<?php

namespace Rector\Php\Tests\Rector\ClassMethod\ReturnTypeDeclarationRector\Fixture;

class ThisClass
{
    /**
     * @return $this
     */
    function someFunction($param, $param2)
    {
        return $param;
    }
}

?>
-----
<?php

namespace Rector\Php\Tests\Rector\ClassMethod\ReturnTypeDeclarationRector\Fixture;

class ThisClass
{
    /**
     * @return $this
     */
    function someFunction($param, $param2): self
    {
        return $param;
    }
}

?>
