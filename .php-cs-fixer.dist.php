<?php
return (new PhpCsFixer\Config())->setRules(['@PSR12'=>true,'no_unused_imports'=>true])->setFinder(PhpCsFixer\Finder::create()->in([__DIR__.'/api/src',__DIR__.'/tests/Unit']));
