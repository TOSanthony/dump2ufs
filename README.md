# dump2ufs

**dump2ufs** converts game dump folders into optimized UFS2 filesystem
images (`.ffpkg`) for use with **ShadowMount v1.4b**.

It is available in three variants:

-   **Docker Web GUI** --- a containerized solution with a browser-based
    interface and conversion queue.
-   **Docker CLI** --- a command-line container for directory and
    archive inputs.
-   **Windows** --- a native PowerShell script for directory inputs,
    using `UFS2Tool.exe`.

## Table of contents

-   [Features](#features)
-   [Docker Web GUI](#docker-web-gui)
    -   [Quick start with Docker
        Compose](#quick-start-with-docker-compose)
    -   [Configuration](#configuration)
-   [Docker CLI](#docker-cli)
    -   [Requirements](#requirements)
    -   [Command-line options](#command-line-options)
    -   [Convert a directory](#convert-a-directory)
    -   [Convert an archive](#convert-an-archive)
    -   [Additional options](#additional-options)
-   [Windows](#windows)
    -   [Features and requirements](#features-and-requirements)
    -   [Command-line options](#windows-command-line-options)
    -   [Examples](#windows-examples)
    -   [Mount archives on Windows
        (advanced)](#mount-archives-on-windows-advanced)
-   [Output and optimization](#output-and-optimization)
-   [Troubleshooting](#troubleshooting)
-   [Credits](#credits)
-   [License](#license)

## Features

-   Converts game dump directories into UFS2 `.ffpkg` images.
-   Supports directory and archive inputs in the Docker CLI version
    (RAR, 7z, and other formats supported by the archive-mounting
    stack).
-   Reads supported archives through FUSE without first extracting their
    contents to a temporary directory.
-   Detects the game root by searching for `sce_sys/param.json`,
    including one directory level below the input root when inspecting
    archives.
-   Tests block sizes from 4 KB to 64 KB and selects the most
    space-efficient option.
-   Generates a UFS volume label from the title ID and game name when no
    label is provided.
-   Supports unattended conversions with the `-y` option.
-   Provides a web interface in the Docker Web GUI edition.

## Docker Web GUI

The Web GUI runs in Docker and provides a browser-accessible interface
for managing game dumps and conversions.

### Quick start with Docker Compose

Create a `docker-compose.yml` file:

``` yaml
services:
  dump2ufs-gui:
    image: ghcr.io/tosanthony/dump2ufs:latest
    container_name: dump2ufs-gui
    ports:
      - "8080:80"
    volumes:
      - /path/to/your/games:/input
      - /path/to/your/outputs:/output
    cap_add:
      - SYS_ADMIN
    devices:
      - /dev/fuse:/dev/fuse
    restart: unless-stopped
```

Replace the two host paths with the directories on your NAS or computer:

-   `/path/to/your/games` --- directory containing your game dump
    folders.
-   `/path/to/your/outputs` --- destination directory for generated
    `.ffpkg` files.

Start the container:

``` bash
docker compose up -d
```

Open the interface in your browser:

``` text
http://<your-nas-ip>:8080
```

### Configuration

  -----------------------------------------------------------------------
  Setting                             Description
  ----------------------------------- -----------------------------------
  `8080:80`                           Publishes the container web server
                                      on port `8080` of the host. Change
                                      `8080` if that port is already in
                                      use.

  `/input`                            Container path where the host game
                                      directory is mounted.

  `/output`                           Container path where generated
                                      images are written.

  `SYS_ADMIN`                         Grants the container the capability
                                      required for FUSE-based archive
                                      access.

  `/dev/fuse`                         Exposes the host FUSE device to the
                                      container.

  `unless-stopped`                    Restarts the container
                                      automatically unless it has been
                                      explicitly stopped.
  -----------------------------------------------------------------------

The Web GUI is intended for directory-based game dump management.
Archive handling through FUSE is also available in the Docker CLI
workflow described below.

## Docker CLI

The CLI image is suitable for scripted and manual conversions.

### Requirements

-   Docker Engine.
-   FUSE3 support on the host when using archive inputs.
-   Access to `/dev/fuse` and permission to use FUSE when converting
    archives.

Directory inputs do not require FUSE.

### Command-line options

The Docker CLI accepts the following arguments:

  -----------------------------------------------------------------------
  Option                              Description
  ----------------------------------- -----------------------------------
  `-i <path>`                         Input file or directory.
                                      **Required.**

  `-o <filename>`                     Output filename. **Required.**

  `-l <label>`                        UFS filesystem label. Optional;
                                      maximum 16 characters.

  `-y`                                Skip the confirmation prompt.
                                      Optional.
  -----------------------------------------------------------------------

If no label is specified, dump2ufs generates one using the title ID and
an alphanumeric version of the game title. For example: `01234MyGame`.

### Convert a directory

``` bash
docker run -it --rm \
  -v /path/to/game:/input \
  -v /path/to/output:/output \
  ghcr.io/tosanthony/dump2ufs:latest \
  -i /input \
  -o game.ffpkg
```

Change `/path/to/game` and `/path/to/output` to the appropriate host
directories.

### Convert an archive

Archive conversion requires FUSE. The archive is mounted and read
without extracting it to a temporary directory.

``` bash
docker run -it --rm \
  --device /dev/fuse \
  --cap-add SYS_ADMIN \
  -v /path/to/game.rar:/game.rar \
  -v /path/to/output:/output \
  ghcr.io/tosanthony/dump2ufs:latest \
  -i /game.rar \
  -o game.ffpkg
```

Replace `/path/to/game.rar` with the full path to your archive. The
archive format must be supported by the image's archive-mounting tools.

### Additional options

This example sets a custom volume label and skips the confirmation
prompt:

``` bash
docker run -it --rm \
  -v /path/to/game:/input \
  -v /path/to/output:/output \
  ghcr.io/tosanthony/dump2ufs:latest \
  -i /input \
  -l "My_Game-01245" \
  -y \
  -o game.ffpkg
```

**WSL2 note:** Writing the output to a Windows-mounted path such as
`/mnt/c/...` works, but may be significantly slower than writing to the
WSL2 filesystem.

## Windows

The Windows edition is a native PowerShell script. It does not require
Docker and accepts directory inputs only.

### Features and requirements

Features:

-   Native PowerShell script.
-   No Docker dependency.
-   Directory input only; archives are not handled directly by the
    script.
-   Uses `UFS2Tool.exe` to create the UFS2 filesystem.
-   Automatically detects suitable block and fragment sizes.
-   Generates a UFS label from the game title and title ID when no
    custom label is supplied.

Requirements:

-   Windows 10 or Windows 11.
-   Windows PowerShell 5.1 or later.
-   [`UFS2Tool.exe`](https://github.com/SvenGDK/UFS2Tool/).

Download `dump2ufs.ps1` and place it somewhere convenient. Make sure
`UFS2Tool.exe` is available in the script's current directory, in a
directory listed in `PATH`, or provide its location with `-u`.

### Windows command-line options

  -----------------------------------------------------------------------
  Option                              Description
  ----------------------------------- -----------------------------------
  `-i`, `-InputPath <path>`           Input game directory. **Required.**

  `-o`, `-OutputFile <filename>`      Output filename. **Required.**

  `-l`, `-Label <label>`              UFS filesystem label. Optional;
                                      maximum 16 characters.

  `-y`, `-SkipConfirmation`           Skip the confirmation prompt.
                                      Optional.

  `-u`, `-UFS2ToolPath <path>`        Path to `UFS2Tool.exe`. Optional;
                                      the script searches the current
                                      directory and `PATH` by default.
  -----------------------------------------------------------------------

### Windows examples

Basic usage:

``` powershell
powershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 `
  -InputPath "C:\Games\PPSA00000-app" `
  -OutputFile "game.ffpkg"
```

Using short aliases:

``` powershell
powershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 `
  -i "C:\Games\PPSA00000-app" `
  -o "game.ffpkg"
```

Set a custom label and skip confirmation:

``` powershell
powershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 `
  -i "C:\Games\PPSA00000-app" `
  -o "game.ffpkg" `
  -l "My_Game-01245" `
  -y
```

Specify the location of `UFS2Tool.exe`:

``` powershell
powershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 `
  -i "C:\Games\PPSA00000-app" `
  -o "game.ffpkg" `
  -u "C:\Tools\UFS2Tool.exe"
```

### Mount archives on Windows (advanced)

The PowerShell script accepts directories, not archive files. However,
it is possible to mount RAR/RAR5 archives as folders on Windows with
**WinFsp**, **Cygwin**, and **rar2fs**, then pass the mounted directory
to dump2ufs.

This is an advanced setup. If you do not need direct archive access,
extract the archive normally and use the resulting directory.

#### 1. Install WinFsp

Download and install the latest stable release from
[WinFsp](https://winfsp.dev/rel/).

#### 2. Install Cygwin

Install [Cygwin](https://www.cygwin.com/) and include these packages:

-   `gcc-g++`
-   `make`
-   `autoconf`
-   `automake`
-   `git`
-   `wget`
-   `tar`

#### 3. Install cygfuse

Open a Cygwin terminal and run:

``` bash
sh "$(cat /proc/registry32/HKEY_LOCAL_MACHINE/SOFTWARE/WinFsp/InstallDir | tr -d '\0')"/opt/cygfuse/install.sh
```

#### 4. Download and compile UnRAR

Download the UnRAR source archive from
[RARLAB](https://www.rarlab.com/rar_add.htm). The commands below use
version `7.2.4` as an example:

``` bash
wget -O - https://www.rarlab.com/rar/unrarsrc-7.2.4.tar.gz | tar -xz
cd unrar

# Add -fPIC for 64-bit compatibility, as described in the rar2fs documentation.
sed -i 's/^CXXFLAGS=-O2/CXXFLAGS=-O2 -fPIC/' makefile

make lib
make install-lib
cd ..
```

#### 5. Download and compile rar2fs

The following example uses rar2fs `1.29.7`:

``` bash
wget -O - https://github.com/hasse69/rar2fs/archive/refs/tags/v1.29.7.tar.gz | tar -xz
cd rar2fs-1.29.7

autoreconf -f -i
./configure
make
make install
cd ..
```

#### 6. Mount the archive

Use Cygwin paths under `/cygdrive/`:

``` bash
mkdir -p /cygdrive/c/temp/archive
rar2fs /cygdrive/c/path/to/archive.rar /cygdrive/c/temp/archive
```

Replace the example archive path with the actual location of your RAR
file.

#### 7. Run dump2ufs from PowerShell

In a Windows terminal, pass the mounted folder to the script:

``` powershell
powershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 `
  -u "C:\Tools\UFS2Tool.exe" `
  -i "C:\temp\archive" `
  -o "game.ffpkg"
```

**Note:** The archive must remain mounted and accessible for the
duration of the conversion.

## Output and optimization

The output is a UFS2 filesystem image with the `.ffpkg` extension.

dump2ufs tests block sizes from **4 KB through 64 KB** and selects the
option that provides the best space efficiency for the input. The
automatically generated filesystem label is for identification; it does
not change how the image is mounted.

For compatibility, use the generated `.ffpkg` image with a ShadowMount
version that supports UFS2 mounting, such as **ShadowMount v1.4b**.

## Troubleshooting

### Docker cannot access `/dev/fuse`

For archive conversion, make sure:

-   The host has FUSE3 installed and enabled.
-   `/dev/fuse` exists on the host.
-   The container is started with `--device /dev/fuse` and
    `--cap-add SYS_ADMIN`.

FUSE is not required for ordinary directory input.

### The game directory is not detected

Check that the game dump contains:

``` text
sce_sys/param.json
```

The Docker CLI archive workflow also checks one directory level below
the archive root for the game directory.

### The output is slow when using WSL2

For better performance, write the output to the Linux filesystem inside
WSL2 rather than a Windows-mounted path such as `/mnt/c/`.

### The Windows script cannot find UFS2Tool.exe

Place `UFS2Tool.exe` beside the script, add its directory to `PATH`, or
pass its full path using `-u`.

## Credits

-   [kusumi/makefs](https://github.com/kusumi/makefs) --- `makefs` from
    FreeBSD 14.3, ported to Linux.
-   [SvenGDK/UFS2Tool](https://github.com/SvenGDK/UFS2Tool) --- Windows
    UFS2 filesystem creation tool.
-   [voidwhisper-ps](https://github.com/voidwhisper-ps) and
    [adel-ailane/ShadowMount](https://github.com/adel-ailane/ShadowMount)
    --- ShadowMount v1.4b and UFS2 mounting support.
-   [earthonion/mkufs2](https://github.com/earthonion/mkufs2) ---
    inspiration for the initial UFS2 creation script.

## License

See the repository's `LICENSE` file for licensing terms. If no `LICENSE`
file is present, all rights remain with their respective owners.
