# --------------------------------
# STAGE 1: Build Kannel from Source (without WMLScript)
# --------------------------------
    FROM ubuntu:20.04 AS builder

    LABEL maintainer="Derrick Kamara <dericking01@email.com>"
    
    ENV DEBIAN_FRONTEND=noninteractive \
        KANNEL_VERSION=1.4.5 \
        KANNEL_PREFIX=/usr/local/kannel
    
    # Install build dependencies
    RUN apt-get update && apt-get install -y --no-install-recommends \
        autoconf automake libtool \
        build-essential gcc make \
        wget curl \
        libpcre3-dev libxml2-dev \
        libmysqlclient-dev libssl-dev \
        flex bison \
        ca-certificates tzdata \
        && rm -rf /var/lib/apt/lists/*
    
    # Download and prepare Kannel source
    WORKDIR /usr/src
    
    ENV CFLAGS="-O2 -U_FORTIFY_SOURCE"

    RUN wget --no-check-certificate https://www.kannel.org/download/1.4.5/gateway-1.4.5.tar.gz \
        && tar -xzf gateway-1.4.5.tar.gz \
        && rm gateway-1.4.5.tar.gz \
        && cd gateway-1.4.5 \
        && sed -i '/^SUBDIRS/s/wmlscript//' Makefile.in \
        && ./configure --prefix=/usr/local/kannel \
        && sed -i '/^SUBDIRS/s/wmlscript//' Makefile \
        && sed -i 's/char discnntbuff\[5\]/char discnntbuff[8]/' gw/smsc/smsc_sema.c \
        && make -j"$(nproc)" \
        && make install
    
    # --------------------------------
    # STAGE 2: Runtime Image
    # --------------------------------
    FROM ubuntu:20.04
    
    ENV DEBIAN_FRONTEND=noninteractive \
        KANNEL_PREFIX=/usr/local/kannel
    
    # Runtime dependencies only
    RUN apt-get update && apt-get install -y --no-install-recommends \
        libpcre3 libxml2 libmysqlclient21 libssl1.1 \
        ca-certificates tzdata net-tools \
        && rm -rf /var/lib/apt/lists/*
    
    # Copy from builder
    COPY --from=builder /usr/local/kannel /usr/local/kannel
    
    # Add entrypoint and config dir
    RUN mkdir -p /etc/kannel /var/log/kannel
    COPY scripts/entrypoint.sh /usr/local/bin/
    RUN chmod +x /usr/local/bin/entrypoint.sh
    
    WORKDIR /usr/local/kannel
    
    EXPOSE 13000 13001 13002
    
    HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
        CMD netstat -tuln | grep -q ':13000' || exit 1
    
    ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
    