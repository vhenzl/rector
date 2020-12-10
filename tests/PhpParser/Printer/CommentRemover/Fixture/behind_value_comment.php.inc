<?php

namespace Rector\Core\Tests\PhpParser\Printer\CommentRemover\Fixture;

$value = 1;// some comment

?>
-----
<?php

namespace Rector\Core\Tests\PhpParser\Printer\CommentRemover\Fixture;

$value = 1;

?>
