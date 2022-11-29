FROM ubuntu:22.04
#FROM ubuntu:16.04

WORKDIR /src

RUN apt-get update && apt-get install -y \
  poppler-utils \
  && rm -rf /var/lib/apt/lists/*

COPY run.sh run.sh

WORKDIR /data

ENTRYPOINT ["/src/run.sh"]
