<?php

namespace Rector\Core\Tests\PhpParser\Printer\CommentRemover\Fixture;

// some comment
$value = 1;

?>
-----
<?php

namespace Rector\Core\Tests\PhpParser\Printer\CommentRemover\Fixture;


$value = 1;

?>
