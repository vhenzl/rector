<?php

namespace Rector\Core\Tests\Exclusion\Check\Fixture;


final class SomeController3
{
    public function foo()
    {

    }
    public function bar()
    {

    }
}

?>
-----
<?php

namespace Rector\Core\Tests\Exclusion\Check\Fixture;


final class SomeController3
{
}

?>
