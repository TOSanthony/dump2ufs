FROM debian:13-slim

ARG MAKEFS_REF=tags/r13
ARG FUSE_ARCHIVE_REF=tags/v1.16

# 1. Installation des outils de compilation ET d'Apache/PHP
RUN apt-get update && \
    apt-get install -y \
    wget \
    jq \
    libfuse3-4 \
    libarchive13t64 \
    gcc \
    g++ \
    make \
    pkg-config \
    pandoc \
    libc6-dev \
    libboost-container-dev \
    libfuse3-dev \
    libarchive-dev \
    apache2 \
    php \
    libapache2-mod-php \
    && \
    # 2. Compilation de makefs
    wget -O - https://github.com/kusumi/makefs/archive/refs/${MAKEFS_REF}.tar.gz | tar -xz -C / && \
    cd /makefs-${MAKEFS_REF##*/} && \
    make USE_HAMMER2=0 USE_EXFAT=0 && \
    make install && \
    # 3. Compilation de fuse-archive
    wget -O - https://github.com/google/fuse-archive/archive/refs/${FUSE_ARCHIVE_REF}.tar.gz | tar -xz -C / && \
    FUSE_DIR=${FUSE_ARCHIVE_REF##*/} && \
    cd /fuse-archive-${FUSE_DIR#v} && \
    make && \
    make install && \
    cd / && \
    # 4. Nettoyage des paquets de compilation pour alléger l'image
    apt-get autoremove --purge -y wget gcc g++ make pkg-config pandoc libc6-dev libboost-container-dev libfuse3-dev libarchive-dev && \
    apt-get clean && \
    rm -rf /makefs-${MAKEFS_REF##*/} /fuse-archive-${FUSE_DIR#v} /var/lib/apt/lists/*

# Copie de vos fichiers web (index.php, etc.) dans le dossier d'Apache
COPY index.php /var/www/html/index.php

# Copie de l'entrypoint si vous l'utilisez en arrière-plan
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configuration d'Apache pour écouter et lancer le service web
EXPOSE 80

# On lance Apache en premier plan pour maintenir le conteneur actif, 
# la GUI PHP se chargera d'appeler entrypoint.sh en arrière-plan lors de la conversion.
CMD ["apache2ctl", "-D", "FOREGROUND"]
