<?php

declare(strict_types=1);

/*
 * This file is part of composer/satis.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PixelgradeLT\Conductor\Client\Builder;

use Composer\IO\IOInterface;

abstract class ComposerBuilder implements ComposerBuilderInterface
{
    /** @var IOInterface The output Interface. */
    protected $output;
    /** @var string The directory where to build. */
    protected $outputDir;
    /** @var array The parameters from ./satis.json. */
    protected $config;
    /** @var bool Skips Exceptions if true. */
    protected $skipErrors;

    public function __construct(IOInterface $output, string $outputDir, array $config, bool $skipErrors)
    {
        $this->output = $output;
        $this->outputDir = $outputDir;
        $this->config = $config;
        $this->skipErrors = $skipErrors;
    }
}
