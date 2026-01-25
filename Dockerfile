FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Add PHP 8.4 repository (Sury PPA)
RUN apt-get update && apt-get install -y \
    software-properties-common \
    && add-apt-repository -y ppa:ondrej/php \
    && rm -rf /var/lib/apt/lists/*

# System dependencies and runtimes
RUN apt-get update && apt-get install -y \
    # Ruby (3.2 from Ubuntu repos)
    ruby-full \
    libyaml-dev \
    # PHP 8.4 from Sury
    php8.4-cli \
    php8.4-curl \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    # Python
    python3 \
    python3-pip \
    python3-venv \
    # Tools
    build-essential \
    ca-certificates \
    curl \
    git \
    imagemagick \
    lame \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Python CLI tools (eyeD3, ia)
RUN pip3 install --break-system-packages eyeD3 internetarchive

# Install Bundler
RUN gem install bundler

# Git safe directory (for mounted volumes with different ownership)
RUN git config --global --add safe.directory /workspace

# Working directory
WORKDIR /workspace
