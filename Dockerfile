FROM debian:13-slim

ARG MAKEFS_REF=tags/r13
ARG FUSE_ARCHIVE_REF=tags/v1.16

# 1. Dépendances système, compilation, serveur Web et prérequis .NET
RUN apt-get update && \
    apt-get install -y \
    ca-certificates \
    curl \
    wget \
    jq \
    fuse3 \
    libfuse3-4 \
    libarchive13t64 \
    libicu-dev \
    libssl-dev \
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
    # 2. Installation de .NET Runtime via le script officiel Microsoft
    curl -sSL https://dot.net/v1/dotnet-install.sh | bash /dev/stdin --runtime dotnet --install-dir /usr/share/dotnet && \
    ln -s /usr/share/dotnet/dotnet /usr/local/bin/dotnet && \
    # 3. Récupération de la dernière release Linux de fpkg-cli
    FPKG_URL=$(curl -s https://api.github.com/repos/SvenGDK/LibProsperoPkg/releases/latest | jq -r '.assets[] | select(.name | test("linux-x64.*tar\\.gz$|linux-x64.*zip$")) | .browser_download_url' | head -n 1) && \
    mkdir -p /opt/fpkg-cli && \
    if [ -n "$FPKG_URL" ]; then \
        wget -qO- "$FPKG_URL" | tar -xz -C /opt/fpkg-cli/ && \
        chmod +x /opt/fpkg-cli/fpkg-cli && \
        ln -s /opt/fpkg-cli/fpkg-cli /usr/local/bin/fpkg-cli; \
    fi && \
    # 4. Compilation de makefs
    wget -O - https://github.com/kusumi/makefs/archive/refs/${MAKEFS_REF}.tar.gz | tar -xz -C / && \
    cd /makefs-${MAKEFS_REF##*/} && \
    make USE_HAMMER2=0 USE_EXFAT=0 && \
    make install && \
    # 5. Compilation de fuse-archive
    wget -O - https://github.com/google/fuse-archive/archive/refs/${FUSE_ARCHIVE_REF}.tar.gz | tar -xz -C / && \
    FUSE_DIR=${FUSE_ARCHIVE_REF##*/} && \
    cd /fuse-archive-${FUSE_DIR#v} && \
    make && \
    make install && \
    cd / && \
    # 6. Nettoyage des dépendances de build
    apt-get autoremove --purge -y gcc g++ make pkg-config pandoc libc6-dev libboost-container-dev libfuse3-dev libarchive-dev && \
    apt-get clean && \
    rm -rf /makefs-${MAKEFS_REF##*/} /fuse-archive-${FUSE_DIR#v} /var/lib/apt/lists/*

# Définition des variables d'environnement .NET
ENV DOTNET_ROOT=/usr/share/dotnet
ENV PATH="${PATH}:/usr/share/dotnet:/opt/fpkg-cli"

# Fichiers applicatifs Web
COPY index.php /var/www/html/index.php
RUN rm -f /var/www/html/index.html

# Entrypoint et scripts
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
