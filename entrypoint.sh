#!/bin/bash
set -euo pipefail

FUSE_PID=""
OUTPUT_TARGET=""
UFS_LABEL=""
NO_CONFIRMATION=false
INPUT_PATH=""
ACTION_MODE="dump_to_ffpkg"

# Points de montage temporaires
MOUNT_DIR="/tmp/.archive_mount"
IMG_MOUNT_DIR="/tmp/.img_mount"
TEMP_EXFAT=""
EXFAT_BUILD_DIR="/tmp/.exfat_build"

# Parse command-line arguments
while getopts "m:o:l:i:y" opt; do
    case $opt in
        m)
            ACTION_MODE="$OPTARG"
            ;;
        o)
            OUTPUT_TARGET="$OPTARG"
            ;;
        l)
            UFS_LABEL="$OPTARG"
            ;;
        i)
            INPUT_PATH="$OPTARG"
            ;;
        y)
            NO_CONFIRMATION=true
            ;;
        *)
            echo "Usage: $0 -i input_path -o output_target [-m action_mode] [-l ufs_label] [-y]"
            exit 1
            ;;
    esac
done

if [ -z "$OUTPUT_TARGET" ] || [ -z "$INPUT_PATH" ]; then
    echo "Error: -i input_path and -o output_target are required"
    exit 1
fi

if [ ! -d /output ]; then
    echo "Error: /output directory does not exist."
    exit 1
fi

mkdir -p "$MOUNT_DIR" "$IMG_MOUNT_DIR"

cleanup() {
    if [ -n "$FUSE_PID" ]; then
        kill "$FUSE_PID" 2>/dev/null || true
        wait "$FUSE_PID" 2>/dev/null || true
    fi
    if mountpoint -q "$MOUNT_DIR" 2>/dev/null; then
        umount "$MOUNT_DIR" 2>/dev/null || true
    fi
    if mountpoint -q "$IMG_MOUNT_DIR" 2>/dev/null; then
        umount "$IMG_MOUNT_DIR" 2>/dev/null || true
    fi
    if mountpoint -q "$EXFAT_BUILD_DIR" 2>/dev/null; then
        umount "$EXFAT_BUILD_DIR" 2>/dev/null || true
    fi
    if [ -n "$TEMP_EXFAT" ] && [ -f "$TEMP_EXFAT" ]; then
        rm -f "$TEMP_EXFAT" 2>/dev/null || true
    fi
    rm -rf "$MOUNT_DIR" "$IMG_MOUNT_DIR" "$EXFAT_BUILD_DIR"
}
trap cleanup EXIT

mount_archive_if_needed() {
    local src="$1"
    if [ -f "$src" ]; then
        echo "Montage de l'archive via fuse-archive..."
        fuse-archive -o nocache,nospecials,nosymlinks,nohardlinks,noxattrs,umask=0000,dmask=0000,fmask=0000,clone_fd -f -v "$src" "$MOUNT_DIR" 2>&1 &
        FUSE_PID=$!
        while true; do
            if ! kill -0 $FUSE_PID 2>/dev/null; then
                echo "Erreur lors du montage fuse-archive."
                exit 1
            fi
            if mountpoint -q "$MOUNT_DIR" 2>/dev/null || [ "$(ls -A "$MOUNT_DIR" 2>/dev/null)" ]; then
                break
            fi
            sleep 0.5
        done
        SOURCE_DIR="$MOUNT_DIR"
        if [ ! -f "$SOURCE_DIR/sce_sys/param.json" ]; then
            while IFS= read -r subdir; do
                if [ -f "$subdir/sce_sys/param.json" ]; then
                    SOURCE_DIR="$subdir"
                    break
                fi
            done < <(find "$MOUNT_DIR" -maxdepth 1 -type d ! -path "$MOUNT_DIR")
        fi
    else
        SOURCE_DIR="$(realpath "$src")"
    fi
}

mount_disk_image() {
    local img="$1"
    echo "Montage loop de l'image disque : $img"
    mount -o loop,ro "$img" "$IMG_MOUNT_DIR"
    SOURCE_DIR="$IMG_MOUNT_DIR"
}

# Fonction helper pour décompresser un .ffpfsc sans bloquer
decompress_ffpfsc() {
    local in_file="$1"
    local out_file="$2"
    echo "Décompression du flux compressé vers $out_file..."
    rm -f "$out_file"
    if ! zstd -d -f --sparse "$in_file" -o "$out_file"; then
        echo "zstd a échoué, essai via fpkg-cli..."
        rm -f "$out_file"
        fpkg-cli decompress --input "$in_file" --output "$out_file"
    fi
}

echo "=== Action demandée : $ACTION_MODE ==="
echo "Entrée : $INPUT_PATH"
echo "Cible  : /output/$OUTPUT_TARGET"

case "$ACTION_MODE" in

    # 1. Création UFS2 (.ffpkg) via makefs
    dump_to_ffpkg)
        mount_archive_if_needed "$INPUT_PATH"

        b_values=(4096 8192 16384 32768 65536)
        best_size=""
        best_b=""
        best_f=""

        for b in "${b_values[@]}"; do
            f=$(( b / 8 ))
            rm -f /tmp/test.out
            output=$(makefs -b 0 -o b=$b,f=$f,m=0,v=2,o=space -s $b /tmp/test.out "$SOURCE_DIR" 2>&1 || true)
            size=$(printf '%s\n' "$output" | sed -n 's/.* size of \([0-9]\+\) .*/\1/p' | head -n1)
            if [[ -n "$size" ]]; then
                if [[ -z "$best_size" || "$size" -lt "$best_size" ]]; then
                    best_size="$size"
                    best_b="$b"
                    best_f="$f"
                fi
            fi
        done

        PARAM_JSON="$SOURCE_DIR/sce_sys/param.json"
        if [ -f "$PARAM_JSON" ] && [ -z "$UFS_LABEL" ]; then
            TITLE_ID=$(jq -r '.titleId // empty' "$PARAM_JSON")
            DEFAULT_LANG=$(jq -r '.localizedParameters.defaultLanguage // empty' "$PARAM_JSON")
            TITLE_NAME=$(jq -r --arg lang "$DEFAULT_LANG" '.localizedParameters[$lang].titleName // .localizedParameters["en-US"].titleName // empty' "$PARAM_JSON")
            TITLE_NAME_CLEAN=$(echo "$TITLE_NAME" | tr -cd 'A-Za-z0-9')
            TITLE_ID_CLEAN=$(echo "$TITLE_ID" | tr -cd 'A-Za-z0-9')
            UFS_LABEL="${TITLE_ID_CLEAN: -5}${TITLE_NAME_CLEAN:0:11}"
        fi

        echo "Génération de l'image UFS2..."
        makefs -b 0 -Z -o "b=$best_b,f=$best_f,m=0,v=2,o=space${UFS_LABEL:+,l=$UFS_LABEL}" "/output/$OUTPUT_TARGET" "$SOURCE_DIR"
        ;;

    # 2. Création image exFAT (.exfat)
    dump_to_exfat)
        mount_archive_if_needed "$INPUT_PATH"
        echo "Calcul de la taille requise..."
        size_kb=$(du -s -B1K "$SOURCE_DIR" | awk '{print $1}')
        total_kb=$(( size_kb + 102400 ))

        OUT_FILE="/output/$OUTPUT_TARGET"
        truncate -s "${total_kb}K" "$OUT_FILE"
        mkfs.exfat "$OUT_FILE"

        mkdir -p "$EXFAT_BUILD_DIR"
        mount -o loop "$OUT_FILE" "$EXFAT_BUILD_DIR"
        cp -a "$SOURCE_DIR"/* "$EXFAT_BUILD_DIR/" 2>/dev/null || true
        umount "$EXFAT_BUILD_DIR"
        ;;

    # 3. Dossier source vers PS5 PKG (.pkg)
    dump_to_pkg)
        echo "Compilation PKG avec fpkg-cli..."
        fpkg-cli create --input "$(realpath "$INPUT_PATH")" --output "/output/$OUTPUT_TARGET"
        ;;

    # 4. Image (.exfat / .ffpfsc) vers PS5 PKG (.pkg)
    image_to_pkg)
        INPUT_TO_MOUNT="$INPUT_PATH"
        if [[ "$INPUT_PATH" =~ \.ffpfsc$ ]]; then
            TEMP_EXFAT="/output/.temp_exfat_${RANDOM}.img"
            decompress_ffpfsc "$INPUT_PATH" "$TEMP_EXFAT"
            INPUT_TO_MOUNT="$TEMP_EXFAT"
        fi

        mount_disk_image "$INPUT_TO_MOUNT"
        echo "Génération du PKG depuis le point de montage..."
        fpkg-cli create --input "$SOURCE_DIR" --output "/output/$OUTPUT_TARGET"
        umount "$IMG_MOUNT_DIR"
        ;;

    # 5. Extraction d'une image (.exfat / .ffpfsc) vers un dossier
    extract_image)
        INPUT_TO_MOUNT="$INPUT_PATH"
        if [[ "$INPUT_PATH" =~ \.ffpfsc$ ]]; then
            TEMP_EXFAT="/output/.temp_exfat_${RANDOM}.img"
            decompress_ffpfsc "$INPUT_PATH" "$TEMP_EXFAT"
            INPUT_TO_MOUNT="$TEMP_EXFAT"
        fi

        mount_disk_image "$INPUT_TO_MOUNT"
        DEST_DIR="/output/$OUTPUT_TARGET"
        mkdir -p "$DEST_DIR"
        echo "Extraction des fichiers vers $DEST_DIR..."
        cp -a "$SOURCE_DIR"/* "$DEST_DIR/"
        umount "$IMG_MOUNT_DIR"
        ;;

    # 6. Extraction d'un package .pkg vers un dossier
    extract_pkg)
        DEST_DIR="/output/$OUTPUT_TARGET"
        mkdir -p "$DEST_DIR"
        echo "Extraction du package avec fpkg-cli..."
        fpkg-cli extract --input "$INPUT_PATH" --output "$DEST_DIR"
        ;;

    # 7. Compression .exfat vers .ffpfsc
    exfat_to_ffpfsc)
        echo "Compression de l'image exFAT vers .ffpfsc..."
        rm -f "/output/$OUTPUT_TARGET"
        zstd -19 -f --sparse "$INPUT_PATH" -o "/output/$OUTPUT_TARGET"
        ;;

    # 8. Décompression .ffpfsc vers .exfat brute
    ffpfsc_to_exfat)
        decompress_ffpfsc "$INPUT_PATH" "/output/$OUTPUT_TARGET"
        ;;

    *)
        echo "Erreur : Mode inconnu '$ACTION_MODE'"
        exit 1
        ;;
esac

echo "Opération terminée avec succès."
