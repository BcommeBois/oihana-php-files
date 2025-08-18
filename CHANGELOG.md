# Oihana PHP Files OpenSource library - Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Added

- Adds oihana\files\enums\FindFilesOption
- Adds oihana\files\enums\RecursiveFilePathsOption

## [1.0.0] - 2025-08-13 

### Added

- Adds oihana\files\assertDirectory
- Adds oihana\files\assertFile
- Adds oihana\files\assertWritableDirectory
- Adds oihana\files\copyFilteredFiles
- Adds oihana\files\deleteDirectory
- Adds oihana\files\deleteTemporaryDirectory
- Adds oihana\files\directoryPath
- Adds oihana\files\findFiles
- Adds oihana\files\getBaseFileName
- Adds oihana\files\getDirectory
- Adds oihana\files\getFileExtension
- Adds oihana\files\getHomeDirectory
- Adds oihana\files\getOwnershipInfos
- Adds oihana\files\getRoot
- Adds oihana\files\getSchemeAndHierarchy
- Adds oihana\files\getTemporaryDirectory
- Adds oihana\files\getTimestampedDirectory
- Adds oihana\files\getTimestampedFile
- Adds oihana\files\hasDirectories
- Adds oihana\files\hasFiles
- Adds oihana\files\isLinux
- Adds oihana\files\isMac
- Adds oihana\files\isOtherOS
- Adds oihana\files\isWindows
- Adds oihana\files\makeDirectory
- Adds oihana\files\makeFile
- Adds oihana\files\makeTimestampedDirectory
- Adds oihana\files\makeTimestampedFile
- Adds oihana\files\recursiveFilePaths
- Adds oihana\files\requireAndMergeArrays
- Adds oihana\files\shouldExcludeFile
- Adds oihana\files\sortFiles
- Adds oihana\files\validMimeType

- Adds oihana\files\archive\tar\assertTar
- Adds oihana\files\archive\tar\hasTarExtension
- Adds oihana\files\archive\tar\hasTarMimeType
- Adds oihana\files\archive\tar\tar
- Adds oihana\files\archive\tar\tarDirectory
- Adds oihana\files\archive\tar\tarFileInfo
- Adds oihana\files\archive\tar\tarIsCompressed
- Adds oihana\files\archive\tar\untar
- Adds oihana\files\archive\tar\validateTarStructure

- Adds oihana\files\enums\CompressionType
- Adds oihana\files\enums\FileExtension
- Adds oihana\files\enums\FileMimeType
- Adds oihana\files\enums\FindFileOption
- Adds oihana\files\enums\FindMode
- Adds oihana\files\enums\MakeDirectoryOption
- Adds oihana\files\enums\MakeFileOption
- Adds oihana\files\enums\OwnershipInfo
- Adds oihana\files\enums\TarExtension
- Adds oihana\files\enums\TarInfo
- Adds oihana\files\enums\TarOption

- Adds oihana\files\exceptions\DirectoryException
- Adds oihana\files\exceptions\FileException
- Adds oihana\files\exceptions\UnsupportedCompressionException
 
- Adds oihana\files\helpers\CanonicalizeBuffer

- Adds oihana\files\openssl\OpenSSLFileEncryption

- Adds oihana\options\Option
- Adds oihana\options\Options

- Adds oihana\files\path\canonicalizePath
- Adds oihana\files\path\computeRelativePath
- Adds oihana\files\path\directoryPath
- Adds oihana\files\path\extractCanonicalParts
- Adds oihana\files\path\isAbsolutePath
- Adds oihana\files\path\isBasePath
- Adds oihana\files\path\isLocalPath
- Adds oihana\files\path\isRelativePath
- Adds oihana\files\path\makeAbsolute
- Adds oihana\files\path\makeRelative
- Adds oihana\files\path\joinPaths
- Adds oihana\files\path\normalizePath
- Adds oihana\files\path\relativePath
- Adds oihana\files\path\splitPath

- Adds oihana\files\phar\assertPhar
- Adds oihana\files\phar\getPharBasePath
- Adds oihana\files\phar\getPharCompressionType
- Adds oihana\files\phar\preservePharFilePermission