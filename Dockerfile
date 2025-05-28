FROM ubuntu:18.04

# Install necessary packages
RUN apt-get update && apt-get install -y \
    nano wget cvs subversion curl git-core zip unzip \
    autoconf automake libtool flex debhelper pkg-config \
    libpam0g-dev intltool checkinstall docbook docbook-xsl \
    build-essential libpcre3 libpcre3-dev libc6-dev g++ gcc \
    autotools-dev bison libncurses5-dev m4 tex-common \
    texi2html texinfo texlive-base texlive-base-bin \
    texlive-latex-base libxml2-dev openssl libssl-dev \
    software-properties-common axel vim mysql-client \
    libmysqlclient-dev

# Install bison 1.28
RUN apt-get remove -y bison && \
    wget --no-check-certificate https://ftp.gnu.org/gnu/bison/bison-1.28.tar.gz && \
    tar -xvf bison-1.28.tar.gz && \
    rm bison-1.28.tar.gz && \
    cd bison-1.28/ && \
    ./configure --prefix=/usr/local/bison --with-libiconv-prefix=/usr/local/libiconv/ && \
    make && make install && \
    ln -s /usr/local/bison/bin/bison /usr/bin/bison && \
    ln -s /usr/local/bison/bin/yacc /usr/bin/yacc

# Setup Kannel user and directories
RUN groupadd kannel && \
    useradd -g kannel kannel && \
    mkdir -p /usr/local/src/kannel /etc/kannel /var/log/kannel && \
    chmod 755 /var/log/kannel

# Install Kannel
WORKDIR /usr/local/src/kannel
RUN wget --no-check-certificate https://www.kannel.org/download/1.4.5/gateway-1.4.5.zip && \
    unzip gateway-1.4.5.zip && \
    rm gateway-1.4.5.zip && \
    mv gateway-1.4.5 gateway

WORKDIR /usr/local/src/kannel/gateway
RUN ./configure --prefix=/usr/local/kannel \
               --with-mysql \
               --with-mysql-dir=/usr/lib/mysql/ \
               --enable-debug \
               --enable-assertions \
               --with-defaults=speed \
               --disable-localtime \
               --enable-start-stop-daemon \
               --enable-pam && \
    touch .depend && \
    make depend && \
    make && \
    chmod 0755 gw-config && \
    make bindir=/usr/local/kannel install

# Add Kannel binaries to PATH
ENV PATH="/usr/local/kannel/sbin:/usr/local/kannel/bin:${PATH}"

COPY kannel.conf /etc/kannel/kannel.conf

# Clean up
RUN apt-get -y clean

# Run as non-root user
USER kannel

# Default command (run bearerbox in foreground)
CMD ["bearerbox", "/etc/kannel/kannel.conf"]