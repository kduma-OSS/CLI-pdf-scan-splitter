FROM ubuntu:22.04
#FROM ubuntu:16.04

WORKDIR /src

RUN apt-get update && apt-get install -y \
  poppler-utils \
  && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
  zbar-tools \
  && rm -rf /var/lib/apt/lists/*

RUN sed -i '/disable ghostscript format types/,+6d' /etc/ImageMagick-6/policy.xml

COPY run.sh run.sh

WORKDIR /data

ENTRYPOINT ["/src/run.sh"]
