# dump2ufs

A tool to convert game folders into optimized UFS2 filesystem images. Works with ShadowMount v1.4b.

Available in multiple versions:
- **Docker Web GUI version**: Containerized solution featuring a modern web interface for easy management and conversions
- **Docker CLI version**: Classic command-line container usage
- **Windows version**: Native PowerShell script, directory input only

## Table of Contents

- [Docker Web GUI version](#docker-web-gui-version)
  - [Features](#features)
  - [Quick Start (Docker Compose)](#quick-start-docker-compose)
- [Docker CLI version](#docker-cli-version)
  - [Features](#features-1)
  - [Requirements](#requirements)
  - [Usage](#usage)
- [Windows version](#windows-version)
  - [Features](#features-2)
  - [Requirements](#requirements-1)
  - [Usage](#usage-1)
  - [Converting archives directly on Windows (optional)](#converting-archives-directly-on-windows-optional)
- [Thanks to](#thanks-to)

---

## Docker Web GUI version

### Features

- Modern dark-themed web interface accessible directly via browser
- Automatically lists game dump folders from your NAS/host input directory
- One-click `.ffpkg` conversion queue processing
- Built-in `makefs` and `fuse-archive` support inside the container

### Quick Start (Docker Compose)

Create a `docker-compose.yml` file with the following configuration:

```yaml
version: '3.8'

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

Then run docker compose up -d and open your browser at http://<your-nas-ip>:8080.   Docker CLI version   Features   Accepts dumps as directories or as archives (RAR, 7z, etc via fuse-archive/libarchive)   Automatically mounts and reads from archives without extracting to a temporary location   Auto-detects game folder root by looking for existence of sce_sys/param.json, checks subfolders (one level deep) in archives   Tests block sizes from 4KB to 64KB for optimal space efficiency and auto-selects the best one   Requirements   Docker   FUSE3 support (only for archive inputs)   Usage   Note: If using with WSL2, writing output to Windows paths (/mnt/c/...) works, but is quite a lot slower.   Command-line options   -i <path>     Input path (file or directory) (required)
-o <filename> Output filename (required)
-l <label>    UFS filesystem label (optional, max 16 chars, does not make any difference for mounting; defaults to title ID number + alphanumeric title name, e.g. 01234MyGame)
-y            Skip confirmation prompt (optional)
Directory inputBashdocker run -it --rm \
  -v /path/to/game/:/input/ \
  -v /path/to/output/:/output/ \
  ghcr.io/tosanthony/dump2ufs -i /input/ -o game.ffpkg
Archive input (requires FUSE)Bashdocker run -it --rm \
  --device /dev/fuse --cap-add SYS_ADMIN \
  -v /path/to/game.rar:/game.rar \
  -v /path/to/output/:/output/ \
  ghcr.io/tosanthony/dump2ufs -i /game.rar -o game.ffpkg
With optional parametersBashdocker run -it --rm \
  -v /path/to/game/:/input/ \
  -v /path/to/output:/output/ \
  ghcr.io/tosanthony/dump2ufs -i /input/ -l "My_Game-01245" -y -o game.ffpkg
Windows versionFeaturesNative PowerShell script (no Docker required)   Directory input only (no archive support)   Uses UFS2Tool.exe for filesystem creation   Automatically detects optimal block/fragment sizes   Auto-generates UFS labels from game title and ID   Note: see below on how one can convert RAR archives to UFS2 on Windows without extracting them first, similarly to the Docker version above.   Requirements   Windows 10/11   PowerShell 5.1 or later (included with Windows)   UFS2Tool.exe by SvenGDK      Usage   Download dump2ufs.ps1 and place it in a convenient location. Ensure UFS2Tool.exe is available.   Command-line options   PowerShell-i, -InputPath <path>      Input directory path (required)[cite: 2]
-o, -OutputFile <filename> Output filename (required)[cite: 2]
-l, -Label <label>         UFS filesystem label (optional, max 16 chars)[cite: 2]
-y -SkipConfirmation       Skip confirmation prompt (optional)[cite: 2]
-u, UFS2ToolPath <path>    Path to UFS2Tool.exe (optional), by default will try current directory and $PATH[cite: 2]
Basic usagePowerShellpowershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 -InputPath "C:\Games\PPSA00000-app" -o "game.ffpkg"
With short aliasesPowerShellpowershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 -i "C:\Games\PPSA00000-app" -o "game.ffpkg"
With custom label and skip confirmationPowerShellpowershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 -i "C:\Games\PPSA00000-app" -o "game.ffpkg" -l "My_Game-01245" -y
Specifying UFS2Tool.exe pathPowerShellpowershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 -i "C:\Games\PPSA00000-app" -o "game.ffpkg" -u "C:\Tools\UFS2Tool.exe"
Converting archives directly on Windows (optional)   To mount RAR/RAR5 archives directly as a folder in Windows, without extracting them first, set up rar2fs:   Install WinFsp (latest stable release)   Install Cygwin with packages: gcc-g++, make, autoconf, automake, git, wget,    tar[cite: 2]Open a Cygwin terminal and run the following commands[cite: 2]:Install cygfuse (FUSE for Cygwin, included with WinFsp)[cite: 2]:Bashsh "$(cat /proc/registry32/HKEY_LOCAL_MACHINE/SOFTWARE/WinFsp/InstallDir | tr -d '\0')"/opt/cygfuse/install.sh
Download and compile UnRAR source v7.x.x[cite: 2]:Bashwget -O - [https://www.rarlab.com/rar/unrarsrc-7.2.4.tar.gz](https://www.rarlab.com/rar/unrarsrc-7.2.4.tar.gz) | tar -xz
cd unrar
# Add -fPIC flag for 64-bit compatibility as per Q 1.7. in rar2fs wiki
sed -i 's/^CXXFLAGS=-O2/CXXFLAGS=-O2 -fPIC/' makefile
make lib
make install-lib
cd ..
Download and compile rar2fs[cite: 2]:   Bashwget -O - [https://github.com/hasse69/rar2fs/archive/refs/tags/v1.29.7.tar.gz](https://github.com/hasse69/rar2fs/archive/refs/tags/v1.29.7.tar.gz) | tar -xz
cd rar2fs-1.29.7
autoreconf -f -i
./configure
make
make install
cd ..
Mount your archive as a folder (using /cygdrive/ paths for Windows compatibility)[cite: 2]:   Bashmkdir -p /cygdrive/c/temp/archive
rar2fs /cygdrive/c/path/to/archive.rar /cygdrive/c/temp/archive
(In a Windows terminal) Use the mounted path with the PowerShell script[cite: 2]:PowerShellpowershell -ExecutionPolicy Bypass -File .\dump2ufs.ps1 -u "C:\Tools\UFS2Tool.exe" -i "C:\temp\archive" -o "game.ffpkg"
Note: This setup is advanced. For simpler usage, extract the archive first, then use this script on the extracted directory[cite: 2].Thanks tokusumi/makefs: for makefs (from FreeBSD 14.3) ported to Linux[cite: 2]SvenGDK/UFS2Tool: for makefs ported to Windows[cite: 2]voidwhisper-ps and adel-ailane/ShadowMount for Shadowmount v1.4b with UFS2 mount support[cite: 2]earthonion/mkufs2: for initial UFS2 creation script[cite: 2]
