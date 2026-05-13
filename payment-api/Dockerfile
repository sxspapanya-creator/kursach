FROM golang:1.23-bookworm AS builder

WORKDIR /src
ENV GOTOOLCHAIN=auto

RUN apt-get update \
    && apt-get install -y --no-install-recommends git ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /payment-api .

FROM alpine:3.20
RUN apk update \
    && apk add --no-cache ca-certificates tzdata \
    && adduser -D appuser

WORKDIR /app
COPY --from=builder /payment-api ./payment-api
USER appuser
EXPOSE 9714
ENTRYPOINT ["/app/payment-api"]
