<?php

declare( strict_types=1 );

/*
 * This file is part of composer/satis.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PixelgradeLT\Conductor\Client\Builder;

use Composer\Composer;
use Composer\Downloader\DownloadManager;
use Composer\Package\Archiver\ArchiveManager;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

class ComposerArchiveBuilder extends ComposerBuilder {
	/** @var Composer A Composer instance. */
	private $composer;

	/**
	 * @param array $packages
	 *
	 * @throws \Exception
	 */
	public function dump( array $packages ): void {
		$helper  = new ComposerArchiveBuilderHelper( $this->output, $this->config['archive'] );
		$basedir = $helper->getDirectory( $this->outputDir );
		$this->output->write( sprintf( "<info>Creating local downloads in '%s'</info>", $basedir ) );
		$endpoint               = $this->config['archive']['prefix-url'] ?? $this->config['homepage'];
		$includeArchiveChecksum = (bool) ( $this->config['archive']['checksum'] ?? true );
		$downloadManager        = $this->composer->getDownloadManager();
		$archiveManager         = $this->composer->getArchiveManager();
		$archiveManager->setOverwriteFiles( false );

		shuffle( $packages );

		/* @var CompletePackage $package */
		foreach ( $packages as $package ) {
			if ( $helper->isSkippable( $package ) ) {
				continue;
			}

			$this->output->write(
				sprintf(
					"<info>Dumping package '%s' in version '%s'.</info>",
					$package->getName(),
					$package->getPrettyVersion()
				)
			);

			try {

				$intermediatePath = preg_replace( '#[^a-z0-9-_/]#i', '-', $package->getName() );

				if ( 'pear-library' === $package->getType() ) {
					/** @see https://github.com/composer/composer/commit/44a4429978d1b3c6223277b875762b2930e83e8c */
					throw new \RuntimeException( 'The PEAR repository has been removed from Composer 2.0' );
				}

				$targetDir     = sprintf( '%s/%s', $basedir, $intermediatePath );
				$path          = $this->archive( $downloadManager, $archiveManager, $package, $targetDir );
				$archiveFormat = pathinfo( $path, PATHINFO_EXTENSION );
				$archive       = basename( $path );
				$distUrl       = sprintf( '%s/%s/%s/%s', $endpoint, $this->config['archive']['directory'], $intermediatePath, $archive );
				$package->setDistType( $archiveFormat );
				$package->setDistUrl( $distUrl );
				$package->setDistSha1Checksum( $includeArchiveChecksum ? hash_file( 'sha1', $path ) : null );
				$package->setDistReference( $package->getSourceReference() );

			} catch ( \Exception $exception ) {
				if ( ! $this->skipErrors ) {
					throw $exception;
				}
				$this->output->write( sprintf( "<error>Skipping Exception '%s'.</error>", $exception->getMessage() ) );
			}
		}
	}

	/**
	 * @param PackageInterface $package
	 *
	 * @throws \Exception
	 * @return string The path to the package archive.
	 */
	public function dumpPackage( PackageInterface $package ): string {
		$helper  = new ComposerArchiveBuilderHelper( $this->output, $this->config['archive'] );
		$basedir = $helper->getDirectory( $this->outputDir );
		$this->output->write( sprintf( "<info>Creating local downloads in '%s'</info>", $basedir ) );
		$downloadManager        = $this->composer->getDownloadManager();
		$archiveManager         = $this->composer->getArchiveManager();
		$archiveManager->setOverwriteFiles( false );

		$this->output->write(
			sprintf(
				"<info>Dumping package '%s' in version '%s'.</info>",
				$package->getName(),
				$package->getPrettyVersion()
			)
		);

		try {

			$intermediatePath = preg_replace( '#[^a-z0-9-_/]#i', '-', $package->getName() );

			if ( 'pear-library' === $package->getType() ) {
				/** @see https://github.com/composer/composer/commit/44a4429978d1b3c6223277b875762b2930e83e8c */
				throw new \RuntimeException( 'The PEAR repository has been removed from Composer 2.0' );
			}

			$targetDir     = sprintf( '%s/%s', $basedir, $intermediatePath );
			$path          = $this->archive( $downloadManager, $archiveManager, $package, $targetDir );
		} catch ( \Exception $exception ) {
			if ( ! $this->skipErrors ) {
				throw $exception;
			}
			$this->output->write( sprintf( "<error>Skipping Exception '%s'.</error>", $exception->getMessage() ) );
		}

		return $path;
	}

	public function setComposer( Composer $composer ): self {
		$this->composer = $composer;

		return $this;
	}

	/**
	 * @param DownloadManager  $downloadManager
	 * @param ArchiveManager   $archiveManager
	 * @param PackageInterface $package
	 * @param string           $targetDir
	 *
	 * @return string
	 */
	private function archive( DownloadManager $downloadManager, ArchiveManager $archiveManager, PackageInterface $package, string $targetDir ): string {
		$format           = (string) ( $this->config['archive']['format'] ?? 'zip' );
		$ignoreFilters    = (bool) ( $this->config['archive']['ignore-filters'] ?? false );
		$overrideDistType = (bool) ( $this->config['archive']['override-dist-type'] ?? false );
		$rearchive        = (bool) ( $this->config['archive']['rearchive'] ?? true );

		$filesystem = new Filesystem();
		$filesystem->ensureDirectoryExists( $targetDir );
		$targetDir = realpath( $targetDir );

		if ( $overrideDistType ) {
			$originalDistType = $package->getDistType();
			$package->setDistType( $format );
			$packageName = $overriddenPackageName = $archiveManager->getPackageFilename( $package );
			$package->setDistType( $originalDistType );
		} else {
			$packageName = $archiveManager->getPackageFilename( $package );
		}

		$path = $targetDir . '/' . $packageName . '.' . $format;
		if ( file_exists( $path ) ) {
			return $path;
		}

		if ( ! $rearchive && in_array( $distType = $package->getDistType(), [ 'tar', 'zip' ], true ) ) {
			if ( $overrideDistType ) {
				$packageName = $archiveManager->getPackageFilename( $package );
			}

			$path = $targetDir . '/' . $packageName . '.' . $distType;
			if ( file_exists( $path ) ) {
				return $path;
			}

			$downloadDir = sys_get_temp_dir() . '/composer_archiver' . uniqid();
			$filesystem->ensureDirectoryExists( $downloadDir );
			$downloader = $downloadManager->getDownloader( 'file' );
			$downloader->download( $package, $downloadDir );

			$filesystem->ensureDirectoryExists( dirname( $path ) );
			$filesystem->rename( $downloadDir . '/' . pathinfo( $package->getDistUrl(), PATHINFO_BASENAME ), $path );
			$filesystem->removeDirectory( $downloadDir );

			return $path;
		}

		if ( $overrideDistType ) {
			$path       = $targetDir . '/' . $packageName . '.' . $format;
			$downloaded = $archiveManager->archive( $package, $format, $targetDir, null, $ignoreFilters );
			$filesystem->rename( $downloaded, $path );

			return $path;
		}

		return $archiveManager->archive( $package, $format, $targetDir, null, $ignoreFilters );
	}
}
