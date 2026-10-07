# ==========================================
# Étape 1 : Compilation des outils C / C++
# ==========================================
FROM debian:13-slim AS builder

ARG MAKEFS_REF=tags/r13
ARG FUSE_ARCHIVE_REF=tags/v1.16

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    wget \
    ca-certificates \
    git \
    gcc \
    g++ \
    make \
    pkg-config \
    libc6-dev \
    libboost-container-dev \
    libfuse3-dev \
    libarchive-dev

# 1. Compilation et installation de makefs
RUN wget -O - https://github.com/kusumi/makefs/archive/refs/${MAKEFS_REF}.tar.gz | tar -xz -C / && \
    cd /makefs-${MAKEFS_REF##*/} && \
    make USE_HAMMER2=0 USE_EXFAT=0 && \
    make install && \
    # makefs s'installe généralement dans /usr/sbin ou /usr/local/sbin
    cp $(find /makefs-${MAKEFS_REF##*/} -name makefs -type f -perm /111 | head -n 1) /usr/local/bin/makefs

# 2. Compilation de fuse-archive
RUN wget -O - https://github.com/google/fuse-archive/archive/refs/${FUSE_ARCHIVE_REF}.tar.gz | tar -xz -C / && \
    FUSE_DIR=${FUSE_ARCHIVE_REF##*/} && \
    cd /fuse-archive-${FUSE_DIR#v} && \
    make VERSION="${FUSE_ARCHIVE_REF##*/}" && \
    cp fuse-archive /usr/local/bin/fuse-archive

# ==========================================
# Étape 2 : Image finale d'exécution
# ==========================================
FROM debian:13-slim

ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive

# 1. Dépendances d'exécution (Apache, PHP, FUSE, utilitaires disques, zstd)
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    wget \
    jq \
    fuse3 \
    libfuse3-4 \
    libarchive13t64 \
    libicu-dev \
    libssl-dev \
    exfatprogs \
    dosfstools \
    zstd \
    apache2 \
    php \
    libapache2-mod-php \
    && rm -rf /var/lib/apt/lists/*

# 2. Récupération des binaires compilés depuis l'étape builder
COPY --from=builder /usr/local/bin/makefs /usr/local/bin/makefs
COPY --from=builder /usr/local/bin/fuse-archive /usr/local/bin/fuse-archive

# 3. Installation du runtime .NET (architecture automatique via le script Microsoft)
RUN curl -sSL https://dot.net/v1/dotnet-install.sh | bash /dev/stdin --runtime dotnet --install-dir /usr/share/dotnet && \
    ln -s /usr/share/dotnet/dotnet /usr/local/bin/dotnet

# 4. Installation de fpkg-cli selon l'architecture cible (amd64 / arm64)
RUN ARCH_PATTERN=$([ "$TARGETARCH" = "arm64" ] && echo "linux-arm64" || echo "linux-x64") && \
    FPKG_URL=$(curl -s https://api.github.com/repos/SvenGDK/LibProsperoPkg/releases/latest | jq -r --arg pat "$ARCH_PATTERN" '.assets[] | select(.name | test($pat + ".*(tar\\.gz|zip)$")) | .browser_download_url' | head -n 1) && \
    mkdir -p /opt/fpkg-cli && \
    if [ -n "$FPKG_URL" ]; then \
        wget -qO- "$FPKG_URL" | tar -xz -C /opt/fpkg-cli/ && \
        chmod +x /opt/fpkg-cli/fpkg-cli && \
        ln -s /opt/fpkg-cli/fpkg-cli /usr/local/bin/fpkg-cli; \
    fi

ENV DOTNET_ROOT=/usr/share/dotnet
ENV PATH="${PATH}:/usr/share/dotnet:/opt/fpkg-cli"

# Fichiers applicatifs Web
COPY index.php /var/www/html/index.php
RUN rm -f /var/www/html/index.html

# Entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
